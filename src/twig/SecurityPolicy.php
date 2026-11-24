<?php

namespace webdna\craftemailcontenteditor\twig;

use craft\elements\db\UserQuery;
use craft\elements\User;
use craft\web\twig\SecurityPolicy as CraftSecurityPolicy;
use Twig\Sandbox\SecurityError;

/**
 * Craft's sandbox policy, plus a blanket refusal of user accounts.
 *
 * Craft marks some user details as safe to read in its sandbox (email, username, last login IP, custom
 * fields). In email content that would let anyone whose text ends up in an email, including customers
 * through their names and addresses, read details of staff accounts such as `entry.author.email`.
 * The recipient's details are available through `recipient` instead.
 */
class SecurityPolicy extends CraftSecurityPolicy
{
    public function checkMethodAllowed($obj, $method): void
    {
        self::refuseUsers($obj);
        parent::checkMethodAllowed($obj, $method);
    }

    public function checkPropertyAllowed($obj, $property): void
    {
        self::refuseUsers($obj);
        parent::checkPropertyAllowed($obj, $property);
    }

    public static function isUser(mixed $value): bool
    {
        return $value instanceof User || $value instanceof UserQuery;
    }

    /**
     * @throws SecurityError
     */
    public static function refuseUsers(mixed $value): void
    {
        if (self::isUser($value)) {
            throw self::usersNotAllowed();
        }
    }

    public static function usersNotAllowed(): SecurityError
    {
        return new SecurityError('User accounts can’t be used in email content. Use `recipient` for the recipient’s details.');
    }
}
