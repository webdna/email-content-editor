<?php

namespace webdna\craftemailcontenteditor\twig;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

/**
 * Wraps an expression so its value passes through IterationGuard::check() before Twig iterates it.
 */
#[YieldReady]
class GuardedExpression extends AbstractExpression
{
    public function __construct(Node $expr)
    {
        parent::__construct(['expr' => $expr], [], $expr->getTemplateLine());
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('\\' . IterationGuard::class . '::check(')
            ->subcompile($this->getNode('expr'))
            ->raw(')');
    }
}
