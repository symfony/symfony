<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class MergeExtensionConfigurationPassTest extends \PHPUnit_Framework_TestCase
{
    public function testUserParametersAreMostImportantThanDefaultOne()
    {
        $container = new ContainerBuilder();
        $container->getParameterBag()->set('key', 'user_value');
        $container->registerExtension(new ExtensionA());
        $container->loadFromExtension('a');
        $container->registerExtension($b = new ExtensionB());
        $container->loadFromExtension('b');

        $pass = new MergeExtensionConfigurationPass();
        $pass->process($container);

        $this->assertSame('user_value', $container->getParameter('key'));
        $this->assertSame('user_value', $b->parameterKey);
    }
}

abstract class Extension implements ExtensionInterface
{
    public function getNamespace()
    {
        return 'http://example.org/schema/dic/'.$this->getAlias();
    }

    public function getXsdValidationBasePath()
    {
        return false;
    }
}

class ExtensionA extends Extension
{
    public function load(array $config, ContainerBuilder $container)
    {
        $container->getParameterBag()->set('key', 'default_value');
    }

    public function getAlias()
    {
        return 'a';
    }
}

class ExtensionB extends Extension
{
    public $parameterKey;

    public function load(array $config, ContainerBuilder $container)
    {
        $this->parameterKey = $container->getParameter('key');
    }

    public function getAlias()
    {
        return 'b';
    }
}
