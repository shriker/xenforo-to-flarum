# XenForo to Flarum

Migration Script from [XenForo](https://xenforo.com/) 1.5 to [Flarum](https://flarum.org/) v0.1.0-beta.8

Discussion https://discuss.flarum.org/d/1117-phpbb-migrate-script-updated-for-0-3-and-other-improvements

This script performs a DB -> DB migration. It will copy all usernames and emails and registration date but *will not copy passwords*. This means after the migration, all users will need to reset their passwords.

## Usage Instructions

1. Create a fresh Flarum using the standard installation instructions.
2. Truncate the following *Flarum* tables:
  ```sql
  SET FOREIGN_KEY_CHECKS = 0;
  truncate discussions;
  truncate discussion_tag;
  truncate discussion_user;
  truncate posts;
  truncate tags;
  truncate users;
  SET FOREIGN_KEY_CHECKS = 1;
  ```
3. Run the script with the correct database parameters for you.

## Contributions Welcome

Thanks to [all who have contributed](https://github.com/shriker/xenforo-to-flarum/graphs/contributors)
