## 3.0.0
* Updated Lightning Core to 3.0, which requires Drupal core 8.6.0.

## 2.4.0
* Locally hosted audio and video files are now supported. Audio support is
  provided by a new component. (Issue #2965767)
* Documents are now stored in folders based on the current date (YYYY-MM).
  (Issue #2958909)
* Fixed a bug where administrator roles provided by Lightning Media had a
  null value for the 'is_admin' flag. (Issue #2882197)
* The "Save to media library" checkbox is now labeled "Show in media library".
  (Issue #2990935)
* All bundled media types now have out-of-the-box support for Pathauto. (#38)

## 2.3.0
No changes since last release.

## 2.2.0
* Updated to Video Embed Field 2.0.

## 2.1.0
* Behat contexts used for testing were moved into the
  `Acquia\LightningExtension\Context` namespace.

## 2.0.0
* Provided an optional update to rename the "Source" filter on the Media
  overview page to "Type".
* Updated Crop API to RC1 and no longer pin it to a specific release.
* Media Entity is no longer used, provided, or patched by Lightning Media.
* In keeping with recent changes in Drupal core, Lightning Media provides an
  update hook that modifies any configured Media-related actions to use the
  new, generic action plugins provided by core.

## 1.0.0-rc3
* Lightning Media will only set up developer-specific settings when our
  internal developer tools are installed.

## 1.0.0-rc2
* Removed legacy update code.

## 1.0.0-rc1
* Allow Media types to be configured without a Source field. (Issue #2928658)
