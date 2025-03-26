<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator;

use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * @implements TransformCallableInterface<A>
 */
class TransformCallable implements TransformCallableInterface
{
    public function __invoke(mixed $value, object $object): mixed
    {
        return "transformed$value";
    }
}
