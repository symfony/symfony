<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Autowires a stack of decorators for an argument.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AutowireStack extends AutowireInline
{
    /**
     * @param array<string|AutowireInline> $stack The decorators, from outermost to innermost
     */
    public function __construct(array $stack)
    {
        if (!$stack) {
            throw new LogicException('#[AutowireStack] attribute cannot declare an empty stack.');
        }

        foreach ($stack as $key => $frame) {
            if ($frame instanceof self) {
                throw new LogicException('#[AutowireStack] attribute cannot contain another stack.');
            }
            if (\is_string($frame)) {
                $stack[$key] = new AutowireInline($frame);
            } elseif (!$frame instanceof AutowireInline) {
                throw new LogicException(\sprintf('Invalid frame "%s" in #[AutowireStack]: expected a class name or an AutowireInline instance, "%s" given.', $key, get_debug_type($frame)));
            }
        }

        Autowire::__construct($stack);
    }

    public function buildDefinition(mixed $value, ?string $type, \ReflectionParameter $parameter): Definition
    {
        foreach ($value as $key => $frame) {
            $value[$key] = $frame->buildDefinition($frame->value, $type, $parameter);
        }

        return (new ChildDefinition(''))
            ->setArguments($value)
            ->addTag('container.stack');
    }
}
