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

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * An attribute to tell how a dependency is used and hint named autowiring aliases.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Target
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = lcfirst(str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9\x7f-\xff]++/', ' ', $name))));
    }

    public static function parseName(\ReflectionParameter $parameter): string
    {
        if (!$target = $parameter->getAttributes(self::class)[0] ?? null) {
            return $parameter->name;
        }

        $name = $target->newInstance()->name;

        if (!preg_match('/^[a-zA-Z_\x7f-\xff]/', $name)) {
            if (($function = $parameter->getDeclaringFunction()) instanceof \ReflectionMethod) {
                $function = $function->class.'::'.$function->name;
            } else {
                $function = $function->name;
            }

            throw new InvalidArgumentException(sprintf('Invalid #[Target] name "%s" on parameter "$%s" of "%s()": the first character must be a letter.', $name, $parameter->name, $function));
        }

        return $name;
    }
}
