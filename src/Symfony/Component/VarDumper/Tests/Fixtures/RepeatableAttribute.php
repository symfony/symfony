<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Fixtures;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_ALL)]
final class RepeatableAttribute
{
    public function __construct(private string $foo = 'default')
    {
    }

    public function getFoo(): string
    {
        return $this->foo;
    }
}
