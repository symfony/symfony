<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator\Tests\Fixtures\Decorator;

use Symfony\Component\Decorator\Attribute\DecoratorAttribute;
use Symfony\Component\Decorator\DecoratorInterface;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Json extends DecoratorAttribute implements DecoratorInterface
{
    public function decorate(\Closure $func): \Closure
    {
        return static function (mixed ...$args) use ($func): string {
            $result = $func(...$args);

            return json_encode($result, JSON_THROW_ON_ERROR);
        };
    }

    public function decoratedBy(): string
    {
        return self::class;
    }
}
