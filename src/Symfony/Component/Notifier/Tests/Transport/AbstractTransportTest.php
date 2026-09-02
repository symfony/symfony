<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;

class AbstractTransportTest extends TestCase
{
    public function testSchemeIsHttpsByDefault()
    {
        $transport = new DummySchemeTransport(new MockHttpClient());

        $this->assertSame('https', $transport->exposedScheme());
    }

    public function testSetSslSwitchesTheScheme()
    {
        $transport = new DummySchemeTransport(new MockHttpClient());

        $this->assertSame('http', $transport->setSsl(false)->exposedScheme());
        $this->assertSame('https', $transport->setSsl(true)->exposedScheme());
    }

    public function testSetSslNullRestoresTheTransportDefault()
    {
        $transport = (new DummySchemeTransport(new MockHttpClient()))->setSsl(false);

        $this->assertSame('https', $transport->setSsl(null)->exposedScheme());
    }

    public function testTransportCanDefaultToHttp()
    {
        $transport = new DummyPlainHttpTransport(new MockHttpClient());

        $this->assertSame('http', $transport->exposedScheme());
        $this->assertSame('https', $transport->setSsl(true)->exposedScheme());
        $this->assertSame('http', $transport->setSsl(null)->exposedScheme());
    }
}

class DummySchemeTransport extends AbstractTransport
{
    public function __toString(): string
    {
        return \sprintf('dummy://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return true;
    }

    public function exposedScheme(): string
    {
        return $this->getHttpScheme();
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}

class DummyPlainHttpTransport extends DummySchemeTransport
{
    protected const SSL = false;
}
