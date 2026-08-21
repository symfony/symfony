<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Security\UserProvider;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\OidcFactory;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class OidcFactoryTest extends TestCase
{
    public function testGetKey()
    {
        $this->assertSame('oidc', (new OidcFactory())->getKey());
    }

    public function testCreateRegistersChildOfBuiltinProvider()
    {
        $container = new ContainerBuilder();

        (new OidcFactory())->create($container, 'security.user.provider.concrete.oidc_users', []);

        $this->assertTrue($container->hasDefinition('security.user.provider.concrete.oidc_users'));
        $definition = $container->getDefinition('security.user.provider.concrete.oidc_users');
        $this->assertInstanceOf(ChildDefinition::class, $definition);
        $this->assertSame('security.user.provider.oidc', $definition->getParent());
    }

    public function testAddConfigurationDefaultsEmptyToEnabled()
    {
        $factory = new OidcFactory();
        $treeBuilder = new TreeBuilder('oidc');
        $factory->addConfiguration($treeBuilder->getRootNode());

        $config = (new Processor())->process($treeBuilder->buildTree(), [null]);

        $this->assertSame(['enabled' => true], $config);
    }
}
