<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TemplatingAssetHelperPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class LegacyTemplatingAssetHelperPassTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);
    }

    public function getScopesTests()
    {
        return array(
            array('container'),
            array('request'),
        );
    }

    /** @dataProvider getScopesTests */
    public function testFindLowestScopeInDefaultPackageWithReference($scope)
    {
        $container = new ContainerBuilder();

        $defaultPackage = new Definition('stdClass');
        $defaultPackage->setScope($scope);
        $container->setDefinition('default_package', $defaultPackage);

        $definition = new Definition('stdClass', array(new Reference('default_package')));
        $container->setDefinition('templating.helper.assets', $definition);

        $profilerPass = new TemplatingAssetHelperPass();
        $profilerPass->process($container);

        $this->assertSame($scope, $definition->getScope());
    }

    /** @dataProvider getScopesTests */
    public function testFindLowestScopeInDefaultPackageWithDefinition($scope)
    {
        $container = new ContainerBuilder();

        $defaultPackage = new Definition('stdClass');
        $defaultPackage->setScope($scope);

        $definition = new Definition('stdClass', array($defaultPackage));
        $container->setDefinition('templating.helper.assets', $definition);

        $profilerPass = new TemplatingAssetHelperPass();
        $profilerPass->process($container);

        $this->assertSame($scope, $definition->getScope());
    }

    /** @dataProvider getScopesTests */
    public function testFindLowestScopeInNamedPackageWithReference($scope)
    {
        $container = new ContainerBuilder();

        $defaultPackage = new Definition('stdClass');
        $container->setDefinition('default_package', $defaultPackage);

        $aPackage = new Definition('stdClass');
        $container->setDefinition('a_package', $aPackage);

        $bPackage = new Definition('stdClass');
        $bPackage->setScope($scope);
        $container->setDefinition('b_package', $bPackage);

        $cPackage = new Definition('stdClass');
        $container->setDefinition('c_package', $cPackage);

        $definition = new Definition('stdClass', array(new Reference('default_package'), array(
            new Reference('a_package'),
            new Reference('b_package'),
            new Reference('c_package'),
        )));
        $container->setDefinition('templating.helper.assets', $definition);

        $profilerPass = new TemplatingAssetHelperPass();
        $profilerPass->process($container);

        $this->assertSame($scope, $definition->getScope());
    }

    /** @dataProvider getScopesTests */
    public function testFindLowestScopeInNamedPackageWithDefinition($scope)
    {
        $container = new ContainerBuilder();

        $defaultPackage = new Definition('stdClass');

        $aPackage = new Definition('stdClass');

        $bPackage = new Definition('stdClass');
        $bPackage->setScope($scope);

        $cPackage = new Definition('stdClass');

        $definition = new Definition('stdClass', array($defaultPackage, array(
            $aPackage,
            $bPackage,
            $cPackage,
        )));
        $container->setDefinition('templating.helper.assets', $definition);

        $profilerPass = new TemplatingAssetHelperPass();
        $profilerPass->process($container);

        $this->assertSame($scope, $definition->getScope());
    }
}
