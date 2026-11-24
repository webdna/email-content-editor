<?php

namespace webdna\craftemailcontenteditor\twig;

use Illuminate\Support\Collection;
use Twig\Environment;
use Twig\Node\CheckToStringNode;
use Twig\Node\Expression\Binary\HasEveryBinary;
use Twig\Node\Expression\Binary\HasSomeBinary;
use Twig\Node\Expression\Binary\InBinary;
use Twig\Node\Expression\Binary\NotInBinary;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\Unary\SpreadUnary;
use Twig\Node\ForNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\Sandbox\SecurityError;
use yii\base\Model;

/**
 * Closes the gap Twig's sandbox leaves around loops.
 *
 * The sandbox checks `a.b` property and method access, but not iteration. Looping over a Craft element or
 * model walks all of its attributes and custom field values without asking the security policy, so it
 * would hand out user accounts and anything else the policy refuses.
 *
 * This node visitor wraps every value Twig iterates (`for` loops, the `...` spread operator, `in`,
 * `has some`/`has every`, and filters that walk a sequence) in a check that refuses models and anything
 * containing user accounts. Arrays, collections and element queries can still be looped over.
 */
class IterationGuard implements NodeVisitorInterface
{
    /** Filters that iterate their input. */
    private const FILTERS = [
        'batch', 'column', 'filter', 'find', 'first', 'join', 'json_encode', 'keys', 'last', 'map', 'merge',
        'reduce', 'reverse', 'shuffle', 'slice', 'sort', 'url_encode',
    ];

    /** Filters that iterate their arguments. */
    private const ARGUMENT_FILTERS = ['merge', 'replace'];

    /**
     * Called from compiled templates on every value that's about to be iterated.
     *
     * @throws SecurityError
     */
    public static function check(mixed $value): mixed
    {
        SecurityPolicy::refuseUsers($value);

        if ($value instanceof Model) {
            throw new SecurityError(sprintf('Looping over a %s isn’t allowed in email content.', $value::class));
        }

        if ((is_array($value) || $value instanceof Collection) && self::containsUser($value)) {
            throw SecurityPolicy::usersNotAllowed();
        }

        return $value;
    }

    private static function containsUser(iterable $items, int $depth = 0): bool
    {
        foreach ($items as $item) {
            if (SecurityPolicy::isUser($item)) {
                return true;
            }
            if ($depth < 3 && (is_array($item) || $item instanceof Collection) && self::containsUser($item, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if ($node instanceof ForNode) {
            $this->wrap($node, 'seq');
        } elseif ($node instanceof SpreadUnary) {
            $this->wrap($node, 'node');
        } elseif ($node instanceof InBinary || $node instanceof NotInBinary) {
            $this->wrap($node, 'right');
        } elseif ($node instanceof HasSomeBinary || $node instanceof HasEveryBinary) {
            $this->wrap($node, 'left');
        } elseif ($node instanceof FilterExpression) {
            $name = $node->getAttribute('name');
            if (in_array($name, self::FILTERS, true)) {
                $this->wrap($node, 'node');
            }
            if (in_array($name, self::ARGUMENT_FILTERS, true)) {
                $arguments = $node->getNode('arguments');
                foreach (array_keys(iterator_to_array($arguments)) as $key) {
                    $this->wrap($arguments, (string)$key);
                }
            }
        }

        return $node;
    }

    private function wrap(Node $node, string $name): void
    {
        if (!$node->hasNode($name)) {
            return;
        }

        // Twig's sandbox may already have wrapped the value in a string check, which turns some values into
        // arrays (e.g. spread operands). Guard the value inside it, so the guard sees the original object.
        $child = $node->getNode($name);
        if ($child instanceof CheckToStringNode) {
            $node = $child;
            $name = 'expr';
        }

        $node->setNode($name, new GuardedExpression($node->getNode($name)));
    }

    /**
     * Runs after Twig's own sandbox visitor (priority 0), so its string checks are already in place.
     */
    public function getPriority(): int
    {
        return 10;
    }
}
