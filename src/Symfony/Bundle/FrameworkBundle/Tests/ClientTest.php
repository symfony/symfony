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

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ClientTest extends WebTestCase
{
    public function testRebootKernelBetweenRequests()
    {
        $mock = $this->getKernelMock();
        $mock->expects($this->once())->method('shutdown');

        $client = new Client($mock);
        $client->request('GET', '/');
        $client->request('GET', '/');
    }

    public function testDisabledRebootKernel()
    {
        $mock = $this->getKernelMock();
        $mock->expects($this->never())->method('shutdown');

        $client = new Client($mock);
        $client->disableReboot();
        $client->request('GET', '/');
        $client->request('GET', '/');
    }

    public function testEnableRebootKernel()
    {
        $mock = $this->getKernelMock();
        $mock->expects($this->once())->method('shutdown');

        $client = new Client($mock);
        $client->disableReboot();
        $client->request('GET', '/');
        $client->request('GET', '/');
        $client->enableReboot();
        $client->request('GET', '/');
    }

    private function getKernelMock()
    {
        $mock = $this->getMockBuilder($this->getKernelClass())
            ->setMethods(['shutdown', 'boot', 'handle'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->any())->method('handle')->willReturn(new Response('foo'));

        return $mock;
    }
}
