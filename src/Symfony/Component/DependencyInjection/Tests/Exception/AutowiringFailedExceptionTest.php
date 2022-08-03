<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;

final class AutowiringFailedExceptionTest extends TestCase
{
    public function testGetMessageCallbackWhenMessageIsNotANotClosure()
    {
        $exception = new AutowiringFailedException(
            'App\DummyService',
            'Cannot autowire service "App\DummyService": argument "$email" of method "__construct()" is type-hinted "string", you should configure its value explicitly.'
        );

        self::assertNull($exception->getMessageCallback());
    }

    public function testLazyness()
    {
        $counter = 0;
        $exception = new AutowiringFailedException(
            'App\DummyService',
            function () use (&$counter) {
                ++$counter;

                throw new \Exception('boo');
            }
        );

        $this->assertSame(0, $counter);

        $this->assertSame('boo', $exception->getMessage());
        $this->assertSame(1, $counter);

        $this->assertSame('boo', $exception->getMessage());
        $this->assertSame(1, $counter);
    }
}
