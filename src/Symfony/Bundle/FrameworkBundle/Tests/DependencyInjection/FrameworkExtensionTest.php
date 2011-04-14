<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

abstract class FrameworkExtensionTest extends TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testCsrfProtection()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->getParameter('form.csrf_protection.enabled'));
        $this->assertEquals('_csrf', $container->getParameter('form.csrf_protection.field_name'));
        $this->assertEquals('s3cr3t', $container->getParameter('form.csrf_protection.secret'));
    }

    public function testEsi()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('esi'), '->registerEsiConfiguration() loads esi.xml');
    }

    public function testProfiler()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('profiler'), '->registerProfilerConfiguration() loads profiling.xml');
        $this->assertTrue($container->hasDefinition('data_collector.config'), '->registerProfilerConfiguration() loads collectors.xml');
        $arguments = $container->getDefinition('profiler_listener')->getArguments();
        $this->assertTrue($arguments[2]);
    }

    public function testRouter()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('router.real'), '->registerRouterConfiguration() loads routing.xml');
        $this->assertEquals($container->getParameter('kernel.root_dir').'/config/routing.xml', $container->getParameter('routing.resource'), '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('xml', $container->getParameter('router.options.resource_type'), '->registerRouterConfiguration() sets routing resource type');
        $this->assertTrue($container->getDefinition('router.cache_warmer')->hasTag('kernel.cache_warmer'), '->registerRouterConfiguration() tags router cache warmer if cache warming is set');
        $this->assertEquals('router.cached', (string) $container->getAlias('router'), '->registerRouterConfiguration() changes router alias to cached if cache warming is set');
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testRouterRequiresResourceOption()
    {
        $container = $this->createContainer();
        $loader = new FrameworkExtension();
        $loader->load(array(array('router' => true)), $container);
    }

    public function testSession()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('session'), '->registerSessionConfiguration() loads session.xml');
        $arguments = $container->getDefinition('session')->getArguments();
        $this->assertEquals('fr', $arguments[1]);
        $this->assertTrue($container->getDefinition('session')->hasMethodCall('start'));
        $this->assertEquals('Session', $container->getParameter('session.class'));
        $this->assertEquals('session.storage.native', (string) $container->getAlias('session.storage'));

        $options = $container->getParameter('session.storage.native.options');
        $this->assertEquals('_SYMFONY', $options['name']);
        $this->assertEquals(86400, $options['lifetime']);
        $this->assertEquals('/', $options['path']);
        $this->assertEquals('example.com', $options['domain']);
        $this->assertTrue($options['secure']);
        $this->assertTrue($options['httponly']);
    }

    public function testSessionPdo()
    {
        $container = $this->createContainerFromFile('session_pdo');
        $options = $container->getParameter('session.storage.pdo.options');

        $this->assertEquals('session.storage.pdo', (string) $container->getAlias('session.storage'));
        $this->assertEquals('table', $options['db_table']);
        $this->assertEquals('id', $options['db_id_col']);
        $this->assertEquals('data', $options['db_data_col']);
        $this->assertEquals('time', $options['db_time_col']);
    }

    public function testTemplating()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('templating.name_parser'), '->registerTemplatingConfiguration() loads templating.xml');
        $arguments = $container->getDefinition('templating.helper.assets')->getArguments();
        $this->assertEquals('SomeVersionScheme', $arguments[2]);
        $this->assertEquals(array('http://cdn.example.com'), $arguments[1]);

        $this->assertTrue($container->getDefinition('templating.cache_warmer.template_paths')->hasTag('kernel.cache_warmer'), '->registerTemplatingConfiguration() tags templating cache warmer if cache warming is set');
        $this->assertEquals('templating.locator.cached', (string) $container->getAlias('templating.locator'), '->registerTemplatingConfiguration() changes templating.locator alias to cached if cache warming is set');

        $this->assertEquals('templating.engine.delegating', (string) $container->getAlias('templating'), '->registerTemplatingConfiguration() configures delegating loader if multiple engines are provided');

        $this->assertEquals($container->getDefinition('templating.loader.chain'), $container->getDefinition('templating.loader.wrapped'), '->registerTemplatingConfiguration() configures loader chain if multiple loaders are provided');

        $this->assertEquals($container->getDefinition('templating.loader'), $container->getDefinition('templating.loader.cache'), '->registerTemplatingConfiguration() configures the loader to use cache');

        $arguments = $container->getDefinition('templating.loader.cache')->getArguments();
        $this->assertEquals('/path/to/cache', $arguments[1]);

        $this->assertEquals(array('php', 'twig'), $container->getParameter('templating.engines'), '->registerTemplatingConfiguration() sets a templating.engines parameter');
    }

    public function testTranslator()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('translator.real'), '->registerTranslatorConfiguration() loads translation.xml');
        $this->assertSame($container->getDefinition('translator.real'), $container->getDefinition('translator'), '->registerTranslatorConfiguration() redefines translator service from identity to real translator');

        $this->assertContains(
            realpath(__DIR__.'/../../Resources/translations/validators.fr.xliff'),
            array_map(function($resource) { return realpath($resource[1]); }, $container->getParameter('translation.resources')),
            '->registerTranslatorConfiguration() finds FrameworkExtension translation resources'
        );

        $calls = $container->getDefinition('translator.real')->getMethodCalls();
        $this->assertEquals('fr', $calls[0][1][0]);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testTemplatingRequiresAtLeastOneEngine()
    {
        $container = $this->createContainer();
        $loader = new FrameworkExtension();
        $loader->load(array(array('templating' => null)), $container);
    }

    public function testValidation()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('validator'), '->registerValidationConfiguration() loads validator.xml');
        $this->assertTrue($container->hasDefinition('validator.mapping.loader.xml_files_loader'), '->registerValidationConfiguration() defines the XML loader');
        $this->assertTrue($container->hasDefinition('validator.mapping.loader.yaml_files_loader'), '->registerValidationConfiguration() defines the YAML loader');

        $xmlLoaderArgs = $container->getDefinition('validator.mapping.loader.xml_files_loader')->getArguments();
        $xmlFiles = $xmlLoaderArgs[0];

        $this->assertContains(
            realpath(__DIR__.'/../../../../Component/Form/Resources/config/validation.xml'),
            array_map('realpath', $xmlFiles),
            '->registerValidationConfiguration() adds Form validation.xml to XML loader'
        );
    }

    public function testValidationAnnotations()
    {
        $container = $this->createContainerFromFile('validation_annotations');

        $this->assertTrue($container->hasDefinition('validator.mapping.loader.annotation_loader'), '->registerValidationConfiguration() defines the annotation loader');

        $arguments = $container->getDefinition('validator.mapping.loader.annotation_loader')->getArguments();
        $this->assertEquals('Symfony\\Component\\Validator\\Constraints\\', $arguments[0]['assert'], '->registerValidationConfiguration() loads the default "assert" prefix');
        $this->assertEquals('Application\\Validator\\Constraints\\', $arguments[0]['app'], '->registerValidationConfiguration() loads custom validation namespaces');
    }

    public function testValidationPaths()
    {
        require_once __DIR__ . "/Fixtures/TestBundle/TestBundle.php";

        $container = $this->createContainerFromFile('validation_annotations', array(
            'kernel.bundles' => array('TestBundle' => 'Symfony\Bundle\FrameworkBundle\Tests\TestBundle'),
        ));

        $yamlArgs = $container->getDefinition('validator.mapping.loader.yaml_files_loader')->getArguments();
        $this->assertEquals(1, count($yamlArgs[0]));
        $this->assertStringEndsWith('TestBundle/Resources/config/validation.yml', $yamlArgs[0][0]);

        $xmlArgs = $container->getDefinition('validator.mapping.loader.xml_files_loader')->getArguments();
        $this->assertEquals(2, count($xmlArgs[0]));
        $this->assertStringEndsWith('Component/Form/Resources/config/validation.xml', $xmlArgs[0][0]);
        $this->assertStringEndsWith('TestBundle/Resources/config/validation.xml', $xmlArgs[0][1]);
    }

    protected function createContainer(array $data = array())
    {
        return new ContainerBuilder(new ParameterBag(array_merge(array(
            'kernel.bundles'          => array('FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'),
            'kernel.cache_dir'        => __DIR__,
            'kernel.compiled_classes' => array(),
            'kernel.debug'            => false,
            'kernel.environment'      => 'test',
            'kernel.name'             => 'kernel',
            'kernel.root_dir'         => __DIR__,
        ), $data)));
    }

    protected function createContainerFromFile($file, $data = array())
    {
        $container = $this->createContainer($data);
        $container->registerExtension(new FrameworkExtension());
        $this->loadFromFile($container, $file);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        return $container;
    }
}
