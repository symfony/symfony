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

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\RegisterBlindIndexesPass;
use Symfony\Component\KeyManagement\Bridge\Flysystem\DependencyInjection\RegisterFlysystemStoragesPass;

class FrameworkBundleTest extends TestCase
{
    /**
     * The pass is what makes the storages of league/flysystem-bundle answer to the host of a
     * "...+fly://" DSN; without it registered, every such DSN names a service the factory has no
     * way of seeing.
     */
    public function testTheFlysystemStoragesPassIsRegisteredWhenTheBridgeIsInstalled()
    {
        if (!class_exists(RegisterFlysystemStoragesPass::class)) {
            $this->markTestSkipped('symfony/flysystem-key-management is not installed.');
        }

        $this->assertContains(RegisterFlysystemStoragesPass::class, $this->buildPasses());
    }

    /**
     * The pass is what hands the listener the blind indexes of the application; without it
     * registered, the listener keeps the empty locator the extension gave it and every entity
     * carrying a "#[BlindIndexed]" property fails on a flush.
     */
    public function testTheBlindIndexesPassIsRegisteredWhenTheBridgeIsInstalled()
    {
        if (!class_exists(RegisterBlindIndexesPass::class)) {
            $this->markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }

        $this->assertContains(RegisterBlindIndexesPass::class, $this->buildPasses());
    }

    /**
     * @return list<string>
     */
    private function buildPasses(): array
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));
        (new FrameworkBundle())->build($container);

        return array_map(get_class(...), $container->getCompiler()->getPassConfig()->getPasses());
    }
}
