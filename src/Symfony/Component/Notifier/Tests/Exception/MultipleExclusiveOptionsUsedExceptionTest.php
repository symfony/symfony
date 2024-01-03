<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\MultipleExclusiveOptionsUsedException;

class MultipleExclusiveOptionsUsedExceptionTest extends TestCase
{
    public function testMessage()
    {
        $exception = new MultipleExclusiveOptionsUsedException(['foo', 'bar'], ['foo', 'bar', 'baz']);

        $this->assertSame(
            'Multiple exclusive options have been used "foo", "bar". Only one of "foo", "bar", "baz" can be used.',
            $exception->getMessage()
        );
    }
}
