# Release Notes for Email Content Editor

## 2.2.0 - Unreleased [CRITICAL]

This is a security release. Twig in email subjects and content could call PHP functions, which let anyone whose text ends up in an email read environment variables and files and run commands. That included customers, through names, addresses and other order details printed by the email's template.

Some existing email content may stop rendering after updating. Before deploying, run `php craft email-content-editor/sandbox/check` on a staging copy of the site. It lists anything the sandbox blocks, for every email entry.

### Security
- Twig in email subjects and content is now rendered in Twig's sandbox, with an allowlist of tags, filters, functions and object properties. PHP functions can no longer be called through filters such as `map`, `filter`, `sort` and `reduce`, and `constant()`, `source()` and `include` are no longer available.
- User accounts can no longer be read from email content (for example `entry.author.email` or `user.email`). Use `recipient` for the recipient's details.
- Objects can no longer be looped over to read their attributes.
- Test data (test recipient, test order, test variables) and the email's Twig are now only applied when someone who can edit the entry previews it. Visiting an email entry's URL no longer shows the test order or test user.
- A test order or test user is only used if the person previewing or sending the test can view it.
- Test emails are now always sent to the person sending them.
- "Send Test Emails" now also requires permission to view the entry being sent.
- The "Set Test Variables" permission is now enforced when the entry is saved, not just by hiding the field.

### Added
- Added the `email-content-editor/sandbox/check` console command, which test-renders every email entry in the sandbox and lists anything it blocks.
- Added the `sandbox` setting, for adding to what email content can use. See `config.php`.

### Changed
- Requires Craft 5.9 or later.
- Email content can only use the tags, filters and functions on the sandbox's allowlist (see `src/twig/Sandbox.php`). Among those no longer available are the `raw`, `format`, `split`, `column`, `batch` and `json_encode` filters, the `autoescape`, `include`, `macro` and `import` tags, the `range`, `constant`, `source`, `include`, `min`, `max` and `cycle` functions, and the `..` operator.
- Commerce orders, line items, addresses, order statuses and order history expose a fixed set of properties to email content. See `src/twig/Sandbox.php` for the list, and use the `sandbox` setting to add more.

### Fixed
- An error in an email's subject no longer stops the email from being sent. The message's own subject is used instead, and the error is logged.
- Fixed a bug where the message for a subject error was run as a shell command instead of being logged.
- Saving an entry without the "Set Test Variables" permission no longer clears its test variables.

## 2.1.4 - 2026-10-23
### Fixed
- Removed a `modifyElementsQuery()` override that called a method Craft 5 no longer has.
- Fixed an undefined variable when an entry's section has no settings for the entry's site.

### Changed
- New plugin icon: a white glyph on a black circle, with a white icon mask for the control panel nav.

## 2.1.3 20/08/26
- Refactor getEmailSettingsFieldHandle method

## 2.1.2 14/11/24
- order.orderSiteId fix

## 2.1.1 08/11/24
- Improve multisite performance, explicitly set the current site for template rendering.

## 2.1.0 29/08/24
- Pass a recipient model to the template instead of the whole User element.
- Previous use of custom fields on the recipient must now be through ``` {{ recipient.customFields.customFieldHandle }} ```. The field handle must be added to the config file for it to be available on the recipient.

## 2.0.1 11/06/24
- Only render the passed in variables when rendering the user content, exclude Craft's global context and Craft variable.
- Add code editor field for the test variables input.
- Remove the ability to render test variables.

## 1.0.15 07/03/24
- Improve situations where the entry-email pair is triggered but the field the is empty.

## 1.0.14 29/02/24
- More naming fixes

## 1.0.13 29/02/24
- Updated plugin name, namespaces etc.
- Updated license

## 1.0.12 28/02/24
- Fixed a bug where getting the orderHistory would cause test commerce emails to send twice

## 1.0.11 27/02/24
- Fix bug with plugin settings
- Remove some unused classes
- Add an example config.php file

## 1.0.10 27/02/24
- Fix margin above subject field

## 1.0.9 27/02/24
- Include orderHistory variable in commerce testVariables
- Fixed situation where recipient variable might be missing from testing scenarios 

## 1.0.8 26/02/24
- Fixed a type error bug when no test order was set for commerce emails
- Improved spacing of email settings field 

## 1.0.7 23/02/24
- Improve error handling after template rendering. Some more fixes for Commerce 4.

## 1.0.6 23/02/24
- Render twig variables in custom fields when viewing the entry on the front end or in live preview.

## 1.0.5 06/02/24
- Fix for when craft commerce is not installed

## 1.0.4 29/01/24
- Added icons

## 1.0.3 29/01/24
- Change repo ownership and namespace

## 1.0.2 29/01/24
- Re-introduce permissions
- Add test variables to template renders/live preview

## 1.0.1 26/01/24
- Change to use an email settings field type rather than section.
- Update for Commerce 4

## 1.0.0
- Initial release
