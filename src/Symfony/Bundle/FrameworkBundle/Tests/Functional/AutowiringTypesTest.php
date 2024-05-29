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

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

class AutowiringTypesTest extends AbstractWebTestCase
{
    public function testEventDispatcherAutowiring()
    {
        static::bootKernel(['debug' => false]);

        $autowiredServices = self::getContainer()->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(EventDispatcher::class, $autowiredServices->getDispatcher(), 'The event_dispatcher service should be injected if the debug is not enabled');

        static::bootKernel(['debug' => true]);

        $autowiredServices = self::getContainer()->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(TraceableEventDispatcher::class, $autowiredServices->getDispatcher(), 'The debug.event_dispatcher service should be injected if the debug is enabled');
    }

    public function testCacheAutowiring()
    {
        static::bootKernel();

        $autowiredServices = self::getContainer()->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(FilesystemAdapter::class, $autowiredServices->getCachePool());
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['test_case' => 'AutowiringTypes'] + $options);
    }
}
