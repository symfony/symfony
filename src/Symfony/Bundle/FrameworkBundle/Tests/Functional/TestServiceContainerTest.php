<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer\NonPublicService;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer\PrivateService;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer\PublicService;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer\TestStateService;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer\UnusedPrivateService;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class TestServiceContainerTest extends AbstractWebTestCase
{
    public function testLogicExceptionIfTestConfigIsDisabled()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer', 'root_config' => 'test_disabled.yml', 'environment' => 'test_disabled']);

        $this->expectException(\LogicException::class);
        static::getContainer();
    }

    public function testThatPrivateServicesAreAvailableIfTestConfigIsEnabled()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $this->assertInstanceOf(TestContainer::class, static::getContainer());
        $this->assertTrue(static::getContainer()->has(PublicService::class));
        $this->assertTrue(static::getContainer()->has(NonPublicService::class));
        $this->assertTrue(static::getContainer()->has(PrivateService::class));
        $this->assertTrue(static::getContainer()->has('private_service'));
        $this->assertFalse(static::getContainer()->has(UnusedPrivateService::class));
    }

    public function testThatPrivateServicesCanBeSetIfTestConfigIsEnabled()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $container = static::getContainer();

        $service = new \stdClass();

        $container->set('private_service', $service);
        $this->assertSame($service, $container->get('private_service'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "private_service" service is already initialized, you cannot replace it.');
        $container->set('private_service', new \stdClass());
    }

    public function testSetDecoratedService()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $container = static::getContainer();

        $service = new PrivateService();
        $container->set('decorated', $service);
        $this->assertSame($service, $container->get('decorated')->inner);
    }

    #[TestWith(['non_shared_service'])]
    #[TestWith(['non_shared_alias'])]
    public function testSetNonSharedService(string $serviceId)
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $container = static::getContainer();

        $services = [$service1 = new \stdClass(), $service2 = new \stdClass()];
        $container->set($serviceId, static function () use (&$services) {
            return array_pop($services);
        });

        $this->assertSame($service2, $container->get(PublicService::class)->nonSharedService);
        $this->assertSame($service1, $container->get(PublicService::class)->nonSharedAlias);
    }

    public function testThrowsExceptionWhenNonSharedServiceIsReplacedByNonCallable()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $container = static::getContainer();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "non_shared_service" service is non-shared and must be replaced by a closure that should act as a factory.');

        $container->set('non_shared_service', new \stdClass());
    }

    public function testServicesTaggedToKeepTheirStateAreCopiedBeforeEachReset()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $container = static::getContainer();
        $service = $container->get('test_state.kept');
        $service->record('foo');

        $container->keepState();
        $container->get('services_resetter')->reset();
        $service->record('bar');
        $container->keepState();
        $container->get('services_resetter')->reset();
        $service->record('baz');

        $this->assertSame([['foo'], ['bar']], array_map(static fn (TestStateService $kept) => $kept->getRecords(), $container->getKeptState('test_state.kept')));
        $this->assertSame(['baz'], $service->getRecords());
    }

    public function testServicesTaggedToKeepTheirStateAreNotInstantiatedToBeCopied()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $container = static::getContainer();
        $container->keepState();

        $this->assertSame([], $container->getKeptState('test_state.kept_but_unused'));
        $this->assertFalse($container->initialized('test_state.kept_but_unused'));
    }

    public function testServicesTaggedToKeepTheirStateAreCopiedBeforeTheClientRebootsTheKernel()
    {
        $client = static::createClient(['test_case' => 'TestServiceContainer']);
        $client->request('GET', '/test_state/foo');
        $client->request('GET', '/test_state/bar');
        $client->request('GET', '/test_state/baz');

        $container = $client->getContainer();
        $this->assertSame([['foo'], ['bar']], array_map(static fn (TestStateService $kept) => $kept->getRecords(), $container->getKeptState('test_state.kept')));
        $this->assertSame(['baz'], $container->get('test_state.kept')->getRecords());
    }

    public function testServicesTaggedToKeepTheirStateAreCopiedBeforeTheClientResetsTheKernel()
    {
        $client = static::createClient(['test_case' => 'TestServiceContainer']);
        $client->disableReboot();
        $client->request('GET', '/test_state/foo');
        $client->request('GET', '/test_state/bar');

        $container = $client->getContainer();
        $this->assertSame([['foo']], array_map(static fn (TestStateService $kept) => $kept->getRecords(), $container->getKeptState('test_state.kept')));
        $this->assertSame(['bar'], $container->get('test_state.kept')->getRecords());
    }

    public function testServicesTaggedWithAMethodAreCalledWhenTheKernelIsShutDown()
    {
        TestStateService::$clearCount = 0;
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $this->assertSame(0, TestStateService::$clearCount);

        static::ensureKernelShutdown();

        $this->assertSame(1, TestStateService::$clearCount);
    }

    public function testTestStateIsIgnoredIfTestConfigIsDisabled()
    {
        TestStateService::$clearCount = 0;
        static::bootKernel(['test_case' => 'TestServiceContainer', 'root_config' => 'test_disabled.yml', 'environment' => 'test_disabled']);
        static::ensureKernelShutdown();

        $this->assertSame(0, TestStateService::$clearCount);
    }

    #[DoesNotPerformAssertions]
    public function testBootKernel()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);
    }

    #[Depends('testBootKernel')]
    public function testKernelIsNotInitialized()
    {
        self::assertNull(self::$class);
        self::assertNull(self::$kernel);
        self::assertFalse(self::$booted);
    }
}
