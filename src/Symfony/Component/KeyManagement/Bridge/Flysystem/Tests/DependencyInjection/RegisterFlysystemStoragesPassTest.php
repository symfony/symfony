<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Flysystem\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\KeyManagement\Bridge\Flysystem\DependencyInjection\RegisterFlysystemStoragesPass;

class RegisterFlysystemStoragesPassTest extends TestCase
{
    public function testAStorageBecomesReachableUnderTheNameTheBundleGaveIt()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('keys.storage', (new Definition(\stdClass::class))->addTag('flysystem.storage', ['storage' => 'keys.storage']));

        (new RegisterFlysystemStoragesPass())->process($container);

        $this->assertSame(
            [['key' => 'keys.storage']],
            $container->getDefinition('keys.storage')->getTag('key_management.flysystem'),
        );
    }

    public function testAStorageWithoutTheAttributeFallsBackToItsServiceId()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.storage', (new Definition(\stdClass::class))->addTag('flysystem.storage'));

        (new RegisterFlysystemStoragesPass())->process($container);

        $this->assertSame(
            [['key' => 'app.storage']],
            $container->getDefinition('app.storage')->getTag('key_management.flysystem'),
        );
    }

    public function testAStorageTaggedByHandIsLeftAlone()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('keys.storage', (new Definition(\stdClass::class))
            ->addTag('flysystem.storage', ['storage' => 'keys.storage'])
            ->addTag('key_management.flysystem', ['key' => 'vault']));

        (new RegisterFlysystemStoragesPass())->process($container);

        $this->assertSame(
            [['key' => 'vault']],
            $container->getDefinition('keys.storage')->getTag('key_management.flysystem'),
        );
    }

    public function testAContainerWithoutFlysystemIsLeftUntouched()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.service', new Definition(\stdClass::class));

        (new RegisterFlysystemStoragesPass())->process($container);

        $this->assertSame([], $container->getDefinition('app.service')->getTag('key_management.flysystem'));
    }
}
