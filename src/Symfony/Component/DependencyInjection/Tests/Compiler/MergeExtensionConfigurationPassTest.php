<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class MergeExtensionConfigurationPassTest extends \PHPUnit_Framework_TestCase
{
    public function testExpressionLanguageProviderForwarding()
    {
        if (true !== class_exists('Symfony\\Component\\ExpressionLanguage\\ExpressionLanguage')) {
            $this->markTestSkipped('The ExpressionLanguage component isn\'t available!');
        }

        $tmpProviders = array();

        $extension = $this->getMock('Symfony\\Component\\DependencyInjection\\Extension\\ExtensionInterface');
        $extension->expects($this->any())
            ->method('getXsdValidationBasePath')
            ->will($this->returnValue(false));
        $extension->expects($this->any())
            ->method('getNamespace')
            ->will($this->returnValue('http://example.org/schema/dic/foo'));
        $extension->expects($this->any())
            ->method('getAlias')
            ->will($this->returnValue('foo'));
        $extension->expects($this->once())
            ->method('load')
            ->will($this->returnCallback(function (array $config, ContainerBuilder $container) use (&$tmpProviders) {
                $tmpProviders = $container->getExpressionLanguageProviders();
            }));

        $provider = $this->getMock('Symfony\\Component\\ExpressionLanguage\\ExpressionFunctionProviderInterface');
        $container = new ContainerBuilder(new ParameterBag());
        $container->registerExtension($extension);
        $container->prependExtensionConfig('foo', array('bar' => true));
        $container->addExpressionLanguageProvider($provider);

        $pass = new MergeExtensionConfigurationPass();
        $pass->process($container);

        $this->assertEquals(array($provider), $tmpProviders);
    }
}
