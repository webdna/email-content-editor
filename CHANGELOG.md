# Release Notes for Email Content Editor

## 1.0.17 29/08/24
- Pass a recipient model to the template instead of the whole User element.
- Previous use of custom fields on the recipient must now be through ``` {{ recipient.customFields.customFieldHandle }} ```. The field handle must be added to the config file for it to be available on the recipient.

## 1.0.16 11/06/24
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
