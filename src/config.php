<?php


/**
 * Email Content Editor config.php
 *
 * This file exists only as a template for the Email Content Editor settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'email-content-editor.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
    // 'customSystemMessages' => [
    //     [
    //         'key' => 'custom-system-message',
    //         'heading' => 'custom system message',
    //         'subject' => 'custom system message',
    //         'body' => 'You can still edit this in utilities'
    //     ]
    // ],
    // define which custom fields from a user should be added to the recipient model
    // 'recipientFields' => []

    // Twig in email subjects and content is rendered in a sandbox that only allows a limited set of tags,
    // filters, functions and object properties. Add to that set here. Each addition is something anyone whose
    // text ends up in an email could use, including customers through their names and addresses, so avoid
    // adding anything that runs code (e.g. `include`), produces raw HTML (`raw`, `autoescape`) or reads user
    // accounts. User accounts are always refused; use `recipient` instead.
    // 'sandbox' => [
    //     'allowedTags' => [],
    //     'allowedFilters' => [],
    //     'allowedFunctions' => [],
    //     // Properties (and their getters) that can be read on a class and its subclasses
    //     'allowedProperties' => [
    //         \craft\commerce\models\LineItem::class => ['weight'],
    //     ],
    //     'allowedMethods' => [],
    // ],
];
