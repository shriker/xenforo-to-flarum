# XenForo to Flarum

Migration Script from [XenForo](https://xenforo.com/) 1.5 to [Flarum](https://flarum.org/) v0.1.0-beta.9

Discussion https://discuss.flarum.org/d/1117-phpbb-migrate-script-updated-for-0-3-and-other-improvements

This script performs a DB -> DB migration. It will copy all usernames and emails and registration date but *will not copy passwords*. This means after the migration, all users will need to reset their passwords.

## Usage Instructions

1. Create a fresh Flarum using the standard installation instructions.
2. Edit `xenforo_connection.php` with your database variables. `xenforo_connection.php` must be uploaded for any of the scripts to work.
3. Upload `xenforo_*` files to your root installation.
4. Truncate the following *Flarum* tables:
  ```sql
  SET FOREIGN_KEY_CHECKS = 0;
  truncate discussions;
  truncate discussion_tag;
  truncate discussion_user;
  truncate group_user;
  truncate posts;
  truncate tags;
  truncate users;
  SET FOREIGN_KEY_CHECKS = 1;
  ```
5. Run your chosen scripts.

## Scripts

| Name | Description |
| ---------------------------- | --------------------------------------------------------------------------------------------- |
| **xenforo_connection.php**   | Where to edit your database connection variables.                                             |
| *xenforo_to_flarum.php*      | Primary migration script for your forum threads and posts.                                    |
| xenforo_tags.php             | Migrate XenForo tags as secondary tags and associate them with imported discussions.          |

## Contributions Welcome

Thanks to [all who have contributed](https://github.com/shriker/xenforo-to-flarum/graphs/contributors)
