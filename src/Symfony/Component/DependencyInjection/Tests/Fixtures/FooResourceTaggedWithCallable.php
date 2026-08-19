<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooResourceTaggedWithCallable implements ResourceTaggedWithCallableInterface
{
    public static function getTagAttributes(): array
    {
        return ['foo' => 'foo'];
    }
}
