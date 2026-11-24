<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Feed Me icon"></p>

# Email Content Editor

A Craft CMS plugin to turn entries into emails.

The plugin works by linking system messages (and commerce emails if the site has them) to entries. When these emails are sent, the content is overridden by the content and template of the entry. The plugin also allows the ability to add new system messages, although how they are triggered must be set up in another plugin/module.

To set up an email-entry pairing create an Email Settings field type and add it to an entry type. When editing the entry, the field provides the options to select a system message (or commerce email), set the subject, and create a json to define any custom variables that should be included for testing. If commerce is installed, it is also possible to choose an order to be injected for testing purposes. 

The plugin also adds a new action button to these emails to send a test email to the current logged in user. As the entry is still just a craft entry, it can still be live previewed or visited at a url provided these have been configured. The test data and the email's Twig are only applied when someone who can edit the entry previews it.

## Twig in email content

Email subjects, and any Twig in the content the entry's template outputs, are rendered in Twig's sandbox. Email content can use:

- `recipient`: `email`, `firstName`, `lastName`, `friendlyName` and `customFields` (for the fields listed in the `recipientFields` setting).
- `entry`: the email entry's title, URL and custom fields.
- For Commerce emails, `order` and `orderHistory`, with a fixed set of properties (see `src/twig/Sandbox.php`).
- The `if`, `for`, `set`, `apply` and `verbatim` tags, and common formatting filters.

User accounts can't be read (use `recipient`), objects can't be looped over, and PHP functions can't be called. To allow more, use the `sandbox` setting described in `src/config.php`.

To check every email entry renders in the sandbox, run:

```bash
php craft email-content-editor/sandbox/check
```

## Requirements

This plugin requires Craft CMS 4.4.0 or later, Craft Commerce 4.0 or later and PHP 8.0.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Email Entries”. Then press “Install”.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require webdna/craft-email-content-editor

# tell Craft to install the plugin
./craft plugin/install email-content-editor
```
