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

abstract class AbstractResourceTaggedWithCallable implements ResourceTaggedWithCallableInterface
{
    public static function getTagAttributes(): array
    {
        return ['foo' => 'abstract'];
    }
}
