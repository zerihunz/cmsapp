<?php

namespace Drupal\content_sync\Form;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Entity\ContentEntityType;

/**
 * Defines the content export form.
 */
trait ContentExportTrait {

  /**
   * @var ArchiveTar
   */
  protected $archiver;

  /**
   * @param $entities
   *
   * @return array
   */
  public function generateBatch($entities) {
    //Set batch operations by entity type/bundle
    $operations = [];
    $operations[] = [[$this, 'generateSiteUUIDFile'], [0 => 0]];
    foreach ($entities as $entity) {
      $entity_to_export = [];
      $entity_to_export['values'][] = $entity;
      $operations[] = [[$this, 'processContentExportFiles'], $entity_to_export];
    }
    if (empty($operations)) {
      $operations[] = [[$this, 'processContentExportFiles'], [0 => 0]];
    }
    //Set Batch
    $batch = [
      'operations' => $operations,
      'finished' => 'finishContentExportBatch',
      'title' => $this->t('Exporting content'),
      'init_message' => $this->t('Starting content export.'),
      'progress_message' => $this->t('Completed @current step of @total.'),
      'error_message' => $this->t('Content export has encountered an error.'),
      'file' => drupal_get_path('module', 'content_sync') . '/content_sync.batch.inc',
    ];
    return $batch;
  }

  /**
   * Processes the content archive export batch
   *
   * @param $files
   *   The batch content to persist.
   * @param array $context
   *   The batch context.
   */
  public function processContentExportFiles($files, &$context) {
    //Initialize Batch
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_number'] = 0;
      $context['sandbox']['max'] = count($files);
    }
    // Get submitted values
    $entity_type = $files[$context['sandbox']['progress']]['entity_type'];
    $entity_id = $files[$context['sandbox']['progress']]['entity_id'];

    //Validate that it is a Content Entity
    $instances = $this->getEntityTypeManager()->getDefinitions();
    if (!(isset($instances[$entity_type]) && $instances[$entity_type] instanceof ContentEntityType)) {
      $context['results']['errors'][] = $this->t('Entity type does not exist or it is not a content instance.') . $entity_type;
    }
    else {
      $entity = $this->getEntityTypeManager()->getStorage($entity_type)
                     ->load($entity_id);
      // Generate the YAML file.
      $serializer_context = [];
      $exported_entity = $this->getContentExporter()
                              ->exportEntity($entity, $serializer_context);
      // Create the name
      $name = $entity_type . "." . $entity->bundle() . "." . $entity->uuid();
      // Create the file.
      $this->getArchiver()->addString("$name.yml", $exported_entity);
      $context['message'] = $name;
      $context['results'][] = $name;
    }
    $context['sandbox']['progress']++;
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Generate UUID YAML file
   * To use for site UUID validation.
   *
   * @param $data
   *   The batch content to persist.
   * @param array $context
   *   The batch context.
   */
  protected function generateSiteUUIDFile($data, &$context) {

    //Include Site UUID to YML file
    $site_config = \Drupal::config('system.site');
    $site_uuid_source = $site_config->get('uuid');
    $entity['site_uuid'] = $site_uuid_source;

    // Set the name
    $name = "site.uuid";
    // Create the file.
    $this->getArchiver()->addString("$name.yml", Yaml::encode($entity));

    //Save to cs_db_snapshot if being called from installer.
    if ($data == 'snapshot') {
      // Insert Data
      $activeStorage = new DatabaseStorage(\Drupal::database(), 'cs_db_snapshot');
      $activeStorage->write($name, $entity);
    }

    $context['message'] = $name;
    $context['results'][] = $name;
    $context['finished'] = 1;
  }

  /**
   * Finish batch.
   *
   * Provide information about the Content Batch results.
   */
  protected function finishContentExportBatch($success, $results, $operations) {
    if ($success) {
      $errors = $results['errors'];
      unset($results['errors']);
      $results = array_unique($results);
      // Log all the items processed
      foreach ($results as $key => $result) {
        if ($key != 'errors') {
          //drupal_set_message(t('Processed UUID @title.', array('@title' => $result)));
          $this->getLogger()
               ->info('Processed UUID @title.', [
                 '@title' => $result,
                 'link' => 'Export',
               ]);
        }
      }
      if (!empty($errors)) {
        // Log the errors
        $errors = array_unique($errors);
        foreach ($errors as $error) {
          //drupal_set_message($error, 'error');
          $this->getLogger()->error($error);
        }
        // Log the note that the content was exported with errors.
        drupal_set_message($this->t('The content was exported with errors. <a href=":content-overview">Logs</a>', [':content-overview' => \Drupal::url('content.overview')]), 'warning');
        $this->getLogger()
             ->warning('The content was exported with errors.', ['link' => 'Export']);
      }
      else {
        // Log the new created export link if applicable.
        drupal_set_message($this->t('The content was exported successfully. <a href=":export-download">Download tar file</a>', [':export-download' => \Drupal::url('content.export_download')]));
        $this->getLogger()
             ->info('The content was exported successfully. <a href=":export-download">Download tar file</a>', [
               ':export-download' => \Drupal::url('content.export_download'),
               'link' => 'Export',
             ]);
      }
    }
    else {
      // Log that there was an error
      $message = $this->t('Finished with an error.<a href=":content-overview">Logs</a>', [':content-overview' => \Drupal::url('content.overview')]);
      drupal_set_message($message);
      $this->getLogger()
           ->error('Finished with an error.', ['link' => 'Export']);
    }
  }

  protected function getArchiver() {
    if (!isset($this->archiver)) {
      $this->archiver = new ArchiveTar($this->getTempFile());
    }
    return $this->archiver;
  }

  protected function getTempFile() {
    return file_directory_temp() . '/content.tar.gz';
  }

  /**
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  abstract protected function getEntityTypeManager();

  /**
   * @return \Drupal\content_sync\Exporter\ContentExporterInterface
   */
  abstract protected function getContentExporter();

  /**
   * @return \Psr\Log\LoggerInterface
   */
  abstract protected function getLogger();


}

