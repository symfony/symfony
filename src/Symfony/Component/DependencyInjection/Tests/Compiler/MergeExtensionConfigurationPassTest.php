<?php
namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class MergeExtensionConfigurationPassTest extends \PHPUnit_Framework_TestCase
{
    public function testExtensionProvidesDefaultParamValues()
    {
        $c = new ContainerBuilder();
        $c->addCompilerPass(new MergeExtensionConfigurationPass());
        $c->registerExtension(new TestExtension());
        $c->registerExtension(new TestExtension2());
        $c->prependExtensionConfig('test_extension', array('test_extension_param_name' => 'test_extension_param_value'));
        $c->prependExtensionConfig('test_extension_2', array('test_extension_2_param_name' => '%param%'));
        $c->setParameter('param', 'overriden_value'); // user use own custom value
        $c->compile();
        $this->assertSame('overriden_value', $c->getParameter('param'));
        $this->assertSame('overriden_value', $c->getParameter('test_extension_2_param_name_2'));
    }
}

class TestExtension extends Extension
{

    /**
     * Loads a specific configuration.
     *
     * @param array $config An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     *
     * @api
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $container->setParameter('param', 'default_value'); // emulate loading default parameters
    }

    public function getAlias()
    {
        return 'test_extension';
    }

}

class TestExtension2 extends Extension
{

    /**
     * Loads a specific configuration.
     *
     * @param array $config An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     *
     * @api
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $container->setParameter('test_extension_2_param_name_2', $config[0]['test_extension_2_param_name']);
    }

    public function getAlias()
    {
        return 'test_extension_2';
    }

}
