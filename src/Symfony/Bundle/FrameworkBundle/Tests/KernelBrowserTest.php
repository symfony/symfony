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

require_once __DIR__.'/Fixtures/gc-function-mock.php';

use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\GcFunctionMock;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\AbstractWebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

class KernelBrowserTest extends AbstractWebTestCase
{
    protected function tearDown(): void
    {
        GcFunctionMock::$status = null;

        parent::tearDown();
    }

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

    #[TestWith([5000, 0])]
    #[TestWith([5001, 1])]
    public function testRebootCollectsCyclesWhenTheRootBufferIsHalfFull(int $roots, int $collections)
    {
        $mock = $this->getKernelMock();
        $mock->expects($this->once())->method('shutdown');

        $client = new KernelBrowser($mock);
        $client->request('GET', '/');
        GcFunctionMock::$status = ['roots' => $roots, 'threshold' => 10001];
        GcFunctionMock::$collections = 0;
        $client->request('GET', '/');

        $this->assertSame($collections, GcFunctionMock::$collections);
    }

    public function testRequestAfterKernelShutdownAndPerformedRequest()
    {
        $this->expectNotToPerformAssertions();

        $client = static::createClient(['test_case' => 'TestServiceContainer']);
        $client->request('GET', '/');
        static::ensureKernelShutdown();
        $client->request('GET', '/');
    }

    public function testGetProfileWithoutRequest()
    {
        $browser = new KernelBrowser($this->createStub(KernelInterface::class));

        $this->assertFalse($browser->getProfile());
    }

    public function testLoginUserPassesTheTokenWithEachRequest()
    {
        $client = static::createClient(['test_case' => 'Security', 'root_config' => 'config.yml']);
        $client->loginUser(new InMemoryUser('stateless-username', 'the-password', ['ROLE_FOO']), 'stateless', ['foo' => 'bar']);

        $client->request('GET', '/stateless/user_profile');
        $client->request('GET', '/stateless/user_profile');

        $token = $client->getRequest()->attributes->get('_security_test_tokens')['stateless'];
        $this->assertInstanceOf(TestBrowserToken::class, $token);
        $this->assertSame('stateless', $token->getFirewallName());
        $this->assertSame('stateless-username', $token->getUserIdentifier());
        $this->assertSame(['foo' => 'bar'], $token->getAttributes());

        $client->restart();
        $client->request('GET', '/stateless/user_profile');

        $this->assertFalse($client->getRequest()->attributes->has('_security_test_tokens'));
    }

    private function getKernelMock()
    {
        $mock = $this->getMockBuilder($this->getKernelClass())
            ->onlyMethods(['shutdown', 'boot', 'handle', 'getContainer'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('handle')->willReturn(new Response('foo'));

        return $mock;
    }
}
