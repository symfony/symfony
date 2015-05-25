<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

require_once __DIR__.'/../Fixtures/includes/PrependExtension.php';

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
        $container->prependExtensionConfig('foo', array('bar' => true ));
        $container->addExpressionLanguageProvider($provider);

        $pass = new MergeExtensionConfigurationPass();
        $pass->process($container);

        $this->assertEquals(array($provider), $tmpProviders);
    }

    public function testPrependingOrderAcrossBundles()
    {
        $one   = array('foo' => 'one');
        $two   = array('foo' => 'two');
        $three = array('foo' => 'three');
        $four  = array('foo' => 'four');
        $five  = array('foo' => 'five');

        $extensionA = $this->getMock('PrependExtension');
        $extensionA->expects($this->any())
            ->method('getAlias')
            ->will($this->returnValue('bundle_a'));
        $extensionA->expects($this->once())
            ->method('prepend')
            ->will($this->returnCallback(function (ContainerBuilder $container) use ($one) {
                $container->prependExtensionConfig('some_bundle', $one);
            }));

        $extensionB = $this->getMock('PrependExtension');
        $extensionB->expects($this->any())
            ->method('getAlias')
            ->will($this->returnValue('bundle_b'));
        $extensionB->expects($this->once())
            ->method('prepend')
            ->will($this->returnCallback(function (ContainerBuilder $container) use ($two) {
                $container->prependExtensionConfig('some_bundle', $two);
            }));

        $extensionC = $this->getMock('PrependExtension');
        $extensionC->expects($this->any())
            ->method('getAlias')
            ->will($this->returnValue('bundle_c'));
        $extensionC->expects($this->once())
            ->method('prepend')
            ->will($this->returnCallback(function (ContainerBuilder $container) use ($three, $four, $five) {
                $container->prependExtensionConfig('some_bundle', $three);
                $container->prependExtensionConfig('some_bundle', $four);
                $container->prependExtensionConfig('some_bundle', $five);
            }));

        $container = new ContainerBuilder(new ParameterBag());
        $container->registerExtension($extensionA);
        $container->registerExtension($extensionB);
        $container->registerExtension($extensionC);

        $pass = new MergeExtensionConfigurationPass();
        $pass->process($container);

        //across extensions the config is FIFO i.e. extension A, B and then C
        //within an extension the config is FILO (hence 3/4/5 are revered)
        $this->assertEquals(array($one, $two, $five, $four, $three), $container->getExtensionConfig('some_bundle'));
    }
}
