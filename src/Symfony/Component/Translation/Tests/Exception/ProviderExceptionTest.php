<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ProviderExceptionTest extends TestCase
{
    public function testExceptionWithDebugMessage()
    {
        $mock = $this->createMock(ResponseInterface::class);
        $mock->method('getInfo')->willReturn('debug');

        $exception = new ProviderException('Exception message', $mock, 503);
        $this->assertSame('debug', $exception->getDebug());
    }

    public function testExceptionWithNullAsDebugMessage()
    {
        $mock = $this->createMock(ResponseInterface::class);
        $mock->method('getInfo')->willReturn(null);

        $exception = new ProviderException('Exception message', $mock, 503);
        $this->assertSame('', $exception->getDebug());
    }
}
