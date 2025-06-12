# TFA SMS Module

A Drupal module that adds SMS-based two-factor authentication using SMS Framework.

## Description

The `tfa_sms` module provides a plugin for the TFA (Two-Factor Authentication) module that enables users to receive verification codes via SMS. The module uses SMS Framework for message delivery and supports various SMS providers (e.g., Twilio).

## Requirements

- Drupal 9/10/11
- [TFA](https://www.drupal.org/project/tfa) module
- [SMS Framework](https://www.drupal.org/project/sms) module
- [Encrypt](https://www.drupal.org/project/encrypt) module
- Configured SMS provider (e.g., Twilio)

## Installation

1. Install the module via Composer:
   ```bash
   composer require drupal/tfa_sms
   ```

2. Enable the module through the admin interface or Drush:
   ```bash
   drush en tfa_sms
   ```

3. Configure your SMS provider in SMS Framework (e.g., Twilio).

4. Navigate to TFA settings (`/admin/config/people/tfa`) and enable the "SMS TFA" plugin.

## Configuration

### Module Settings

1. Navigate to module settings (`/admin/config/people/tfa/sms`).
2. Optionally enable debug mode.

### Phone Field

The module automatically creates a `field_phone_number` for users if it doesn't exist. If you already have SMS Framework installed, the module will use its phone field.

To add the phone field manually:

1. Navigate to user field settings (`/admin/config/people/accounts/fields`).
2. Add a new field of type "Phone".
3. Name the field `field_phone_number`.
4. Configure field visibility and accessibility in the profile edit form.

## Debugging

### Enabling Debug Mode

1. Navigate to module settings (`/admin/config/people/tfa/sms`).
2. Enable the "Enable debug logging" option.

### Viewing Logs

In debug mode, the module logs detailed information to the `tfa_sms` channel. You can view logs:

1. Through the admin interface: `/admin/reports/dblog`
2. Via Drush:
   ```bash
   drush ws --type=tfa_sms
   ```

### Testing SMS Sending

The module settings include a "Test SMS sending" section where you can:

1. Enter a test phone number
2. Enter a test message
3. Send a test SMS

In debug mode, SMS messages are only logged and not actually sent.

## Features

- SMS-based two-factor authentication
- Support for multiple SMS providers through SMS Framework
- Automatic phone field creation
- Debug mode for testing
- Secure code generation
- Login attempt logging
- Rate limiting for verification attempts

## Security

- Codes are generated using a cryptographically secure random number generator
- All login attempts are logged
- Rate limiting is supported
- Phone numbers are validated
- Integration with Drupal's encryption system

## Support

If you encounter issues or have suggestions for improving the module, please create an issue in the project's issue tracking system.

## Contributing

Contributions are welcome! Please feel free to submit pull requests or create issues for bugs and feature requests. 