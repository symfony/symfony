<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures\DataCollector;

class DummyController
{
    /**
     * Dummy method used as controller callable.
     */
    public static function staticControllerMethod()
    {
        throw new \LogicException('Unexpected method call');
    }

    /**
     * Magic method to allow non existing methods to be called and delegated.
     */
    public function __call(string $method, array $args)
    {
        throw new \LogicException('Unexpected method call');
    }

    /**
     * Magic method to allow non existing methods to be called and delegated.
     */
    public static function __callStatic(string $method, array $args)
    {
        throw new \LogicException('Unexpected method call');
    }

    public function __invoke()
    {
        throw new \LogicException('Unexpected method call');
    }

    public function regularCallable()
    {
        throw new \LogicException('Unexpected method call');
    }
}
