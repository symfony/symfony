<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\AbstractWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class KernelBrowserTest extends AbstractWebTestCase
{
    public function testRebootKernelBetweenRequests()
    {
        $mock = $this->getKernelMock();
        $mock->expects($this->once())->method('shutdown');

        $client = new KernelBrowser($mock);
        $client->request('GET', '/');
        $client->request('GET', '/');
    }

    public function testDisabledRebootKernel()
    {
        $mock = $this->getKernelMock();
        $mock->expects($this->never())->method('shutdown');

        $client = new KernelBrowser($mock);
        $client->disableReboot();
        $client->request('GET', '/');
        $client->request('GET', '/');
    }

    public function testEnableRebootKernel()
    {
        $mock = $this->getKernelMock();
        $mock->expects($this->once())->method('shutdown');

        $client = new KernelBrowser($mock);
        $client->disableReboot();
        $client->request('GET', '/');
        $client->request('GET', '/');
        $client->enableReboot();
        $client->request('GET', '/');
    }

    public function testRequestAfterKernelShutdownAndPerformedRequest()
    {
        $this->expectNotToPerformAssertions();

        $client = static::createClient(['test_case' => 'TestServiceContainer']);
        $client->request('GET', '/');
        static::ensureKernelShutdown();
        $client->request('GET', '/');
    }

    private function getKernelMock()
    {
        $mock = $this->getMockBuilder($this->getKernelClass())
            ->onlyMethods(['shutdown', 'boot', 'handle', 'getContainer'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->any())->method('handle')->willReturn(new Response('foo'));

        return $mock;
    }
}
