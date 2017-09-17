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

use Doctrine\Common\Annotations\Annotation;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslatorPass;
use Symfony\Bundle\FullStack;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddAnnotationsCachedReaderPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\DoctrineAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass;

abstract class FrameworkExtensionTest extends TestCase
{
    private static $containerCache = array();

    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testFormCsrfProtection()
    {
        $container = $this->createContainerFromFile('full');

        $def = $container->getDefinition('form.type_extension.csrf');

        $this->assertTrue($container->getParameter('form.type_extension.csrf.enabled'));
        $this->assertEquals('%form.type_extension.csrf.enabled%', $def->getArgument(1));
        $this->assertEquals('_csrf', $container->getParameter('form.type_extension.csrf.field_name'));
        $this->assertEquals('%form.type_extension.csrf.field_name%', $def->getArgument(2));
    }

    public function testPropertyAccessWithDefaultValue()
    {
        $container = $this->createContainerFromFile('full');

        $def = $container->getDefinition('property_accessor');
        $this->assertFalse($def->getArgument(0));
        $this->assertFalse($def->getArgument(1));
    }

    public function testPropertyAccessWithOverriddenValues()
    {
        $container = $this->createContainerFromFile('property_accessor');
        $def = $container->getDefinition('property_accessor');
        $this->assertTrue($def->getArgument(0));
        $this->assertTrue($def->getArgument(1));
    }

    public function testPropertyAccessCache()
    {
        $container = $this->createContainerFromFile('property_accessor');

        if (!method_exists(PropertyAccessor::class, 'createCache')) {
            return $this->assertFalse($container->hasDefinition('cache.property_access'));
        }

        $cache = $container->getDefinition('cache.property_access');
        $this->assertSame(array(PropertyAccessor::class, 'createCache'), $cache->getFactory(), 'PropertyAccessor::createCache() should be used in non-debug mode');
        $this->assertSame(AdapterInterface::class, $cache->getClass());
    }

    public function testPropertyAccessCacheWithDebug()
    {
        $container = $this->createContainerFromFile('property_accessor', array('kernel.debug' => true));

        if (!method_exists(PropertyAccessor::class, 'createCache')) {
            return $this->assertFalse($container->hasDefinition('cache.property_access'));
        }

        $cache = $container->getDefinition('cache.property_access');
        $this->assertNull($cache->getFactory());
        $this->assertSame(ArrayAdapter::class, $cache->getClass(), 'ArrayAdapter should be used in debug mode');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage CSRF protection needs sessions to be enabled.
     */
    public function testCsrfProtectionNeedsSessionToBeEnabled()
    {
        $this->createContainerFromFile('csrf_needs_session');
    }

    public function testCsrfProtectionForFormsEnablesCsrfProtectionAutomatically()
    {
        $container = $this->createContainerFromFile('csrf');

        $this->assertTrue($container->hasDefinition('security.csrf.token_manager'));
    }

    public function testHttpMethodOverride()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertFalse($container->getParameter('kernel.http_method_override'));
    }

    public function testEsi()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('esi'), '->registerEsiConfiguration() loads esi.xml');
    }

    public function testSsi()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('ssi'), '->registerSsiConfiguration() loads ssi.xml');
    }

    public function testEnabledProfiler()
    {
        $container = $this->createContainerFromFile('profiler');

        $this->assertTrue($container->hasDefinition('profiler'), '->registerProfilerConfiguration() loads profiling.xml');
        $this->assertTrue($container->hasDefinition('data_collector.config'), '->registerProfilerConfiguration() loads collectors.xml');
    }

    public function testDisabledProfiler()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertFalse($container->hasDefinition('profiler'), '->registerProfilerConfiguration() does not load profiling.xml');
        $this->assertFalse($container->hasDefinition('data_collector.config'), '->registerProfilerConfiguration() does not load collectors.xml');
    }

    public function testWorkflows()
    {
        $container = $this->createContainerFromFile('workflows');

        $this->assertTrue($container->hasDefinition('workflow.article', 'Workflow is registered as a service'));
        $this->assertTrue($container->hasDefinition('workflow.article.definition', 'Workflow definition is registered as a service'));

        $workflowDefinition = $container->getDefinition('workflow.article.definition');

        $this->assertSame(
            array(
                'draft',
                'wait_for_journalist',
                'approved_by_journalist',
                'wait_for_spellchecker',
                'approved_by_spellchecker',
                'published',
            ),
            $workflowDefinition->getArgument(0),
            'Places are passed to the workflow definition'
        );
        $this->assertSame(array('workflow.definition' => array(array('name' => 'article', 'type' => 'workflow', 'marking_store' => 'multiple_state'))), $workflowDefinition->getTags());

        $this->assertTrue($container->hasDefinition('state_machine.pull_request', 'State machine is registered as a service'));
        $this->assertTrue($container->hasDefinition('state_machine.pull_request.definition', 'State machine definition is registered as a service'));
        $this->assertCount(4, $workflowDefinition->getArgument(1));
        $this->assertSame('draft', $workflowDefinition->getArgument(2));

        $stateMachineDefinition = $container->getDefinition('state_machine.pull_request.definition');

        $this->assertSame(
            array(
                'start',
                'coding',
                'travis',
                'review',
                'merged',
                'closed',
            ),
            $stateMachineDefinition->getArgument(0),
            'Places are passed to the state machine definition'
        );
        $this->assertSame(array('workflow.definition' => array(array('name' => 'pull_request', 'type' => 'state_machine', 'marking_store' => 'single_state'))), $stateMachineDefinition->getTags());
        $this->assertCount(9, $stateMachineDefinition->getArgument(1));
        $this->assertSame('start', $stateMachineDefinition->getArgument(2));

        $serviceMarkingStoreWorkflowDefinition = $container->getDefinition('workflow.service_marking_store_workflow');
        /** @var Reference $markingStoreRef */
        $markingStoreRef = $serviceMarkingStoreWorkflowDefinition->getArgument(1);
        $this->assertInstanceOf(Reference::class, $markingStoreRef);
        $this->assertEquals('workflow_service', (string) $markingStoreRef);

        $this->assertTrue($container->hasDefinition('workflow.registry', 'Workflow registry is registered as a service'));
        $registryDefinition = $container->getDefinition('workflow.registry');
        $this->assertGreaterThan(0, count($registryDefinition->getMethodCalls()));
    }

    /**
     * @group legacy
     * @expectedDeprecation The "type" option of the "framework.workflows.missing_type" configuration entry must be defined since Symfony 3.3. The default value will be "state_machine" in Symfony 4.0.
     */
    public function testDeprecatedWorkflowMissingType()
    {
        $container = $this->createContainerFromFile('workflows_without_type');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage "type" and "service" cannot be used together.
     */
    public function testWorkflowCannotHaveBothTypeAndService()
    {
        $this->createContainerFromFile('workflow_with_type_and_service');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage "supports" and "support_strategy" cannot be used together.
     */
    public function testWorkflowCannotHaveBothSupportsAndSupportStrategy()
    {
        $this->createContainerFromFile('workflow_with_support_and_support_strategy');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage "supports" or "support_strategy" should be configured.
     */
    public function testWorkflowShouldHaveOneOfSupportsAndSupportStrategy()
    {
        $this->createContainerFromFile('workflow_without_support_and_support_strategy');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage "arguments" and "service" cannot be used together.
     */
    public function testWorkflowCannotHaveBothArgumentsAndService()
    {
        $this->createContainerFromFile('workflow_with_arguments_and_service');
    }

    public function testWorkflowMultipleTransitionsWithSameName()
    {
        $container = $this->createContainerFromFile('workflow_with_multiple_transitions_with_same_name');

        $this->assertTrue($container->hasDefinition('workflow.article', 'Workflow is registered as a service'));
        $this->assertTrue($container->hasDefinition('workflow.article.definition', 'Workflow definition is registered as a service'));

        $workflowDefinition = $container->getDefinition('workflow.article.definition');

        $transitions = $workflowDefinition->getArgument(1);

        $this->assertCount(5, $transitions);

        $this->assertSame('request_review', $transitions[0]->getArgument(0));
        $this->assertSame('journalist_approval', $transitions[1]->getArgument(0));
        $this->assertSame('spellchecker_approval', $transitions[2]->getArgument(0));
        $this->assertSame('publish', $transitions[3]->getArgument(0));
        $this->assertSame('publish', $transitions[4]->getArgument(0));

        $this->assertSame(array('approved_by_journalist', 'approved_by_spellchecker'), $transitions[3]->getArgument(1));
        $this->assertSame(array('draft'), $transitions[4]->getArgument(1));
    }

    public function testRouter()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->has('router'), '->registerRouterConfiguration() loads routing.xml');
        $arguments = $container->findDefinition('router')->getArguments();
        $this->assertEquals($container->getParameter('kernel.root_dir').'/config/routing.xml', $container->getParameter('router.resource'), '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('%router.resource%', $arguments[1], '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('xml', $arguments[2]['resource_type'], '->registerRouterConfiguration() sets routing resource type');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
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
        $this->assertEquals('fr', $container->getParameter('kernel.default_locale'));
        $this->assertEquals('session.storage.native', (string) $container->getAlias('session.storage'));
        $this->assertEquals('session.handler.native_file', (string) $container->getAlias('session.handler'));

        $options = $container->getParameter('session.storage.options');
        $this->assertEquals('_SYMFONY', $options['name']);
        $this->assertEquals(86400, $options['cookie_lifetime']);
        $this->assertEquals('/', $options['cookie_path']);
        $this->assertEquals('example.com', $options['cookie_domain']);
        $this->assertTrue($options['cookie_secure']);
        $this->assertFalse($options['cookie_httponly']);
        $this->assertTrue($options['use_cookies']);
        $this->assertEquals(108, $options['gc_divisor']);
        $this->assertEquals(1, $options['gc_probability']);
        $this->assertEquals(90000, $options['gc_maxlifetime']);

        $this->assertEquals('/path/to/sessions', $container->getParameter('session.save_path'));
    }

    public function testNullSessionHandler()
    {
        $container = $this->createContainerFromFile('session');

        $this->assertTrue($container->hasDefinition('session'), '->registerSessionConfiguration() loads session.xml');
        $this->assertNull($container->getDefinition('session.storage.native')->getArgument(1));
        $this->assertNull($container->getDefinition('session.storage.php_bridge')->getArgument(0));
    }

    public function testRequest()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('request.add_request_formats_listener'), '->registerRequestConfiguration() loads request.xml');
        $listenerDef = $container->getDefinition('request.add_request_formats_listener');
        $this->assertEquals(array('csv' => array('text/csv', 'text/plain'), 'pdf' => array('application/pdf')), $listenerDef->getArgument(0));
    }

    public function testEmptyRequestFormats()
    {
        $container = $this->createContainerFromFile('request');

        $this->assertFalse($container->hasDefinition('request.add_request_formats_listener'), '->registerRequestConfiguration() does not load request.xml when no request formats are defined');
    }

    public function testTemplating()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('templating.name_parser'), '->registerTemplatingConfiguration() loads templating.xml');

        $this->assertEquals('templating.engine.delegating', (string) $container->getAlias('templating'), '->registerTemplatingConfiguration() configures delegating loader if multiple engines are provided');

        $this->assertEquals($container->getDefinition('templating.loader.chain'), $container->getDefinition('templating.loader.wrapped'), '->registerTemplatingConfiguration() configures loader chain if multiple loaders are provided');

        $this->assertEquals($container->getDefinition('templating.loader'), $container->getDefinition('templating.loader.cache'), '->registerTemplatingConfiguration() configures the loader to use cache');

        $this->assertEquals('%templating.loader.cache.path%', $container->getDefinition('templating.loader.cache')->getArgument(1));
        $this->assertEquals('/path/to/cache', $container->getParameter('templating.loader.cache.path'));

        $this->assertEquals(array('php', 'twig'), $container->getParameter('templating.engines'), '->registerTemplatingConfiguration() sets a templating.engines parameter');

        $this->assertEquals(array('FrameworkBundle:Form', 'theme1', 'theme2'), $container->getParameter('templating.helper.form.resources'), '->registerTemplatingConfiguration() registers the theme and adds the base theme');
        $this->assertEquals('global_hinclude_template', $container->getParameter('fragment.renderer.hinclude.global_template'), '->registerTemplatingConfiguration() registers the global hinclude.js template');
    }

    public function testTemplatingCanBeDisabled()
    {
        $container = $this->createContainerFromFile('templating_disabled');

        $this->assertFalse($container->hasParameter('templating.engines'), '"templating.engines" container parameter is not registered when templating is disabled.');
    }

    public function testAssets()
    {
        $container = $this->createContainerFromFile('assets');
        $packages = $container->getDefinition('assets.packages');

        // default package
        $defaultPackage = $container->getDefinition((string) $packages->getArgument(0));
        $this->assertUrlPackage($container, $defaultPackage, array('http://cdn.example.com'), 'SomeVersionScheme', '%%s?version=%%s');

        // packages
        $packages = $packages->getArgument(1);
        $this->assertCount(6, $packages);

        $package = $container->getDefinition((string) $packages['images_path']);
        $this->assertPathPackage($container, $package, '/foo', 'SomeVersionScheme', '%%s?version=%%s');

        $package = $container->getDefinition((string) $packages['images']);
        $this->assertUrlPackage($container, $package, array('http://images1.example.com', 'http://images2.example.com'), '1.0.0', '%%s?version=%%s');

        $package = $container->getDefinition((string) $packages['foo']);
        $this->assertPathPackage($container, $package, '', '1.0.0', '%%s-%%s');

        $package = $container->getDefinition((string) $packages['bar']);
        $this->assertUrlPackage($container, $package, array('https://bar2.example.com'), 'SomeVersionScheme', '%%s?version=%%s');

        $package = $container->getDefinition((string) $packages['bar_version_strategy']);
        $this->assertEquals('assets.custom_version_strategy', (string) $package->getArgument(1));

        $package = $container->getDefinition((string) $packages['json_manifest_strategy']);
        $versionStrategy = $container->getDefinition((string) $package->getArgument(1));
        $this->assertEquals('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        $this->assertEquals('/path/to/manifest.json', $versionStrategy->getArgument(0));
    }

    public function testAssetsDefaultVersionStrategyAsService()
    {
        $container = $this->createContainerFromFile('assets_version_strategy_as_service');
        $packages = $container->getDefinition('assets.packages');

        // default package
        $defaultPackage = $container->getDefinition((string) $packages->getArgument(0));
        $this->assertEquals('assets.custom_version_strategy', (string) $defaultPackage->getArgument(1));
    }

    public function testWebLink()
    {
        $container = $this->createContainerFromFile('web_link');
        $this->assertTrue($container->hasDefinition('web_link.add_link_header_listener'));
    }

    public function testTranslator()
    {
        $container = $this->createContainerFromFile('full');
        $this->assertTrue($container->hasDefinition('translator.default'), '->registerTranslatorConfiguration() loads translation.xml');
        $this->assertEquals('translator.default', (string) $container->getAlias('translator'), '->registerTranslatorConfiguration() redefines translator service from identity to real translator');
        $options = $container->getDefinition('translator.default')->getArgument(4);

        $files = array_map('realpath', $options['resource_files']['en']);
        $ref = new \ReflectionClass('Symfony\Component\Validator\Validation');
        $this->assertContains(
            strtr(dirname($ref->getFileName()).'/Resources/translations/validators.en.xlf', '/', DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Validator translation resources'
        );
        $ref = new \ReflectionClass('Symfony\Component\Form\Form');
        $this->assertContains(
            strtr(dirname($ref->getFileName()).'/Resources/translations/validators.en.xlf', '/', DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Form translation resources'
        );
        $ref = new \ReflectionClass('Symfony\Component\Security\Core\Security');
        $this->assertContains(
            strtr(dirname($ref->getFileName()).'/Resources/translations/security.en.xlf', '/', DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Security translation resources'
        );
        $this->assertContains(
            strtr(__DIR__.'/Fixtures/translations/test_paths.en.yml', '/', DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds translation resources in custom paths'
        );

        $calls = $container->getDefinition('translator.default')->getMethodCalls();
        $this->assertEquals(array('fr'), $calls[1][1][0]);
    }

    public function testTranslatorMultipleFallbacks()
    {
        $container = $this->createContainerFromFile('translator_fallbacks');

        $calls = $container->getDefinition('translator.default')->getMethodCalls();
        $this->assertEquals(array('en', 'fr'), $calls[1][1][0]);
    }

    public function testTranslatorHelperIsRegisteredWhenTranslatorIsEnabled()
    {
        $container = $this->createContainerFromFile('templating_php_translator_enabled');

        $this->assertTrue($container->has('templating.helper.translator'));
    }

    public function testTranslatorHelperIsNotRegisteredWhenTranslatorIsDisabled()
    {
        $container = $this->createContainerFromFile('templating_php_translator_disabled');

        $this->assertFalse($container->has('templating.helper.translator'));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
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

        $ref = new \ReflectionClass('Symfony\Component\Form\Form');
        $xmlMappings = array(dirname($ref->getFileName()).'/Resources/config/validation.xml');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $annotations = !class_exists(FullStack::class) && class_exists(Annotation::class);

        $this->assertCount($annotations ? 7 : 6, $calls);
        $this->assertSame('setConstraintValidatorFactory', $calls[0][0]);
        $this->assertEquals(array(new Reference('validator.validator_factory')), $calls[0][1]);
        $this->assertSame('setTranslator', $calls[1][0]);
        $this->assertEquals(array(new Reference('translator')), $calls[1][1]);
        $this->assertSame('setTranslationDomain', $calls[2][0]);
        $this->assertSame(array('%validator.translation_domain%'), $calls[2][1]);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        $this->assertSame(array($xmlMappings), $calls[3][1]);
        $i = 3;
        if ($annotations) {
            $this->assertSame('enableAnnotationMapping', $calls[++$i][0]);
        }
        $this->assertSame('addMethodMapping', $calls[++$i][0]);
        $this->assertSame(array('loadValidatorMetadata'), $calls[$i][1]);
        $this->assertSame('setMetadataCache', $calls[++$i][0]);
        $this->assertEquals(array(new Reference('validator.mapping.cache.symfony')), $calls[$i][1]);
    }

    public function testValidationService()
    {
        $container = $this->createContainerFromFile('validation_annotations', array('kernel.charset' => 'UTF-8'), false);

        $this->assertInstanceOf('Symfony\Component\Validator\Validator\ValidatorInterface', $container->get('validator'));
    }

    public function testAnnotations()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertEquals($container->getParameter('kernel.cache_dir').'/annotations', $container->getDefinition('annotations.filesystem_cache')->getArgument(0));
        $this->assertSame('annotations.filesystem_cache', (string) $container->getDefinition('annotations.cached_reader')->getArgument(1));
    }

    public function testFileLinkFormat()
    {
        if (ini_get('xdebug.file_link_format') || get_cfg_var('xdebug.file_link_format')) {
            $this->markTestSkipped('A custom file_link_format is defined.');
        }

        $container = $this->createContainerFromFile('full');

        $this->assertEquals('file%link%format', $container->getParameter('debug.file_link_format'));
    }

    public function testValidationAnnotations()
    {
        $container = $this->createContainerFromFile('validation_annotations');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertCount(7, $calls);
        $this->assertSame('enableAnnotationMapping', $calls[4][0]);
        $this->assertEquals(array(new Reference('annotation_reader')), $calls[4][1]);
        $this->assertSame('addMethodMapping', $calls[5][0]);
        $this->assertSame(array('loadValidatorMetadata'), $calls[5][1]);
        $this->assertSame('setMetadataCache', $calls[6][0]);
        $this->assertEquals(array(new Reference('validator.mapping.cache.symfony')), $calls[6][1]);
        // no cache this time
    }

    public function testValidationPaths()
    {
        require_once __DIR__.'/Fixtures/TestBundle/TestBundle.php';

        $container = $this->createContainerFromFile('validation_annotations', array(
            'kernel.bundles' => array('TestBundle' => 'Symfony\\Bundle\\FrameworkBundle\\Tests\\TestBundle'),
            'kernel.bundles_metadata' => array('TestBundle' => array('namespace' => 'Symfony\\Bundle\\FrameworkBundle\\Tests', 'parent' => null, 'path' => __DIR__.'/Fixtures/TestBundle')),
        ));

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertCount(8, $calls);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        $this->assertSame('addYamlMappings', $calls[4][0]);
        $this->assertSame('enableAnnotationMapping', $calls[5][0]);
        $this->assertSame('addMethodMapping', $calls[6][0]);
        $this->assertSame(array('loadValidatorMetadata'), $calls[6][1]);
        $this->assertSame('setMetadataCache', $calls[7][0]);
        $this->assertEquals(array(new Reference('validator.mapping.cache.symfony')), $calls[7][1]);

        $xmlMappings = $calls[3][1][0];
        $this->assertCount(2, $xmlMappings);
        try {
            // Testing symfony/symfony
            $this->assertStringEndsWith('Component'.DIRECTORY_SEPARATOR.'Form/Resources/config/validation.xml', $xmlMappings[0]);
        } catch (\Exception $e) {
            // Testing symfony/framework-bundle with deps=high
            $this->assertStringEndsWith('symfony'.DIRECTORY_SEPARATOR.'form/Resources/config/validation.xml', $xmlMappings[0]);
        }
        $this->assertStringEndsWith('TestBundle/Resources/config/validation.xml', $xmlMappings[1]);

        $yamlMappings = $calls[4][1][0];
        $this->assertCount(1, $yamlMappings);
        $this->assertStringEndsWith('TestBundle/Resources/config/validation.yml', $yamlMappings[0]);
    }

    public function testValidationPathsUsingCustomBundlePath()
    {
        require_once __DIR__.'/Fixtures/CustomPathBundle/src/CustomPathBundle.php';

        $container = $this->createContainerFromFile('validation_annotations', array(
            'kernel.bundles' => array('CustomPathBundle' => 'Symfony\\Bundle\\FrameworkBundle\\Tests\\CustomPathBundle'),
            'kernel.bundles_metadata' => array('TestBundle' => array('namespace' => 'Symfony\\Bundle\\FrameworkBundle\\Tests', 'parent' => null, 'path' => __DIR__.'/Fixtures/CustomPathBundle')),
        ));

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();
        $xmlMappings = $calls[3][1][0];
        $this->assertCount(2, $xmlMappings);

        try {
            // Testing symfony/symfony
            $this->assertStringEndsWith('Component'.DIRECTORY_SEPARATOR.'Form/Resources/config/validation.xml', $xmlMappings[0]);
        } catch (\Exception $e) {
            // Testing symfony/framework-bundle with deps=high
            $this->assertStringEndsWith('symfony'.DIRECTORY_SEPARATOR.'form/Resources/config/validation.xml', $xmlMappings[0]);
        }
        $this->assertStringEndsWith('CustomPathBundle/Resources/config/validation.xml', $xmlMappings[1]);

        $yamlMappings = $calls[4][1][0];
        $this->assertCount(1, $yamlMappings);
        $this->assertStringEndsWith('CustomPathBundle/Resources/config/validation.yml', $yamlMappings[0]);
    }

    public function testValidationNoStaticMethod()
    {
        $container = $this->createContainerFromFile('validation_no_static_method');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $annotations = !class_exists(FullStack::class) && class_exists(Annotation::class);

        $this->assertCount($annotations ? 6 : 5, $calls);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        $i = 3;
        if ($annotations) {
            $this->assertSame('enableAnnotationMapping', $calls[++$i][0]);
        }
        $this->assertSame('setMetadataCache', $calls[++$i][0]);
        $this->assertEquals(array(new Reference('validator.mapping.cache.symfony')), $calls[$i][1]);
        // no cache, no annotations, no static methods
    }

    public function testValidationMapping()
    {
        $container = $this->createContainerFromFile('validation_mapping');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertSame('addXmlMappings', $calls[3][0]);
        $this->assertCount(2, $calls[3][1][0]);

        $this->assertSame('addYamlMappings', $calls[4][0]);
        $this->assertCount(3, $calls[4][1][0]);
        $this->assertContains('foo.yml', $calls[4][1][0][0]);
        $this->assertContains('validation.yml', $calls[4][1][0][1]);
        $this->assertContains('validation.yaml', $calls[4][1][0][2]);
    }

    public function testFormsCanBeEnabledWithoutCsrfProtection()
    {
        $container = $this->createContainerFromFile('form_no_csrf');

        $this->assertFalse($container->getParameter('form.type_extension.csrf.enabled'));
    }

    public function testStopwatchEnabledWithDebugModeEnabled()
    {
        $container = $this->createContainerFromFile('default_config', array(
            'kernel.container_class' => 'foo',
            'kernel.debug' => true,
        ));

        $this->assertTrue($container->has('debug.stopwatch'));
    }

    public function testStopwatchEnabledWithDebugModeDisabled()
    {
        $container = $this->createContainerFromFile('default_config', array(
            'kernel.container_class' => 'foo',
        ));

        $this->assertTrue($container->has('debug.stopwatch'));
    }

    public function testSerializerDisabled()
    {
        $container = $this->createContainerFromFile('default_config');
        $this->assertSame(!class_exists(FullStack::class) && class_exists(Serializer::class), $container->has('serializer'));
    }

    public function testSerializerEnabled()
    {
        $container = $this->createContainerFromFile('full');
        $this->assertTrue($container->has('serializer'));

        $argument = $container->getDefinition('serializer.mapping.chain_loader')->getArgument(0);

        $this->assertCount(1, $argument);
        $this->assertEquals('Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader', $argument[0]->getClass());
        $this->assertNull($container->getDefinition('serializer.mapping.class_metadata_factory')->getArgument(1));
        $this->assertEquals(new Reference('serializer.name_converter.camel_case_to_snake_case'), $container->getDefinition('serializer.normalizer.object')->getArgument(1));
        $this->assertEquals(new Reference('property_info', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE), $container->getDefinition('serializer.normalizer.object')->getArgument(3));
    }

    public function testRegisterSerializerExtractor()
    {
        $container = $this->createContainerFromFile('full');

        $serializerExtractorDefinition = $container->getDefinition('property_info.serializer_extractor');

        $this->assertEquals('serializer.mapping.class_metadata_factory', $serializerExtractorDefinition->getArgument(0)->__toString());
        $this->assertFalse($serializerExtractorDefinition->isPublic());
        $tag = $serializerExtractorDefinition->getTag('property_info.list_extractor');
        $this->assertEquals(array('priority' => -999), $tag[0]);
    }

    public function testDataUriNormalizerRegistered()
    {
        if (!class_exists('Symfony\Component\Serializer\Normalizer\DataUriNormalizer')) {
            $this->markTestSkipped('The DataUriNormalizer has been introduced in the Serializer Component version 3.1.');
        }

        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.data_uri');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(DataUriNormalizer::class, $definition->getClass());
        $this->assertEquals(-920, $tag[0]['priority']);
    }

    public function testDateTimeNormalizerRegistered()
    {
        if (!class_exists('Symfony\Component\Serializer\Normalizer\DateTimeNormalizer')) {
            $this->markTestSkipped('The DateTimeNormalizer has been introduced in the Serializer Component version 3.1.');
        }

        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.datetime');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(DateTimeNormalizer::class, $definition->getClass());
        $this->assertEquals(-910, $tag[0]['priority']);
    }

    public function testJsonSerializableNormalizerRegistered()
    {
        if (!class_exists('Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer')) {
            $this->markTestSkipped('The JsonSerializableNormalizer has been introduced in the Serializer Component version 3.1.');
        }

        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.json_serializable');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(JsonSerializableNormalizer::class, $definition->getClass());
        $this->assertEquals(-900, $tag[0]['priority']);
    }

    public function testObjectNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.object');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals('Symfony\Component\Serializer\Normalizer\ObjectNormalizer', $definition->getClass());
        $this->assertEquals(-1000, $tag[0]['priority']);
    }

    public function testSerializerCacheActivated()
    {
        if (!class_exists(CacheClassMetadataFactory::class) || !method_exists(XmlFileLoader::class, 'getMappedClasses') || !method_exists(YamlFileLoader::class, 'getMappedClasses')) {
            $this->markTestSkipped('The Serializer default cache warmer has been introduced in the Serializer Component version 3.2.');
        }

        $container = $this->createContainerFromFile('serializer_enabled');

        $this->assertTrue($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));

        $cache = $container->getDefinition('serializer.mapping.cache_class_metadata_factory')->getArgument(1);
        $this->assertEquals(new Reference('serializer.mapping.cache.symfony'), $cache);
    }

    public function testSerializerCacheDisabled()
    {
        $container = $this->createContainerFromFile('serializer_enabled', array('kernel.debug' => true, 'kernel.container_class' => __CLASS__));
        $this->assertFalse($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
    }

    /**
     * @group legacy
     * @expectedDeprecation The "framework.serializer.cache" option is deprecated %s.
     */
    public function testDeprecatedSerializerCacheOption()
    {
        $container = $this->createContainerFromFile('serializer_legacy_cache', array('kernel.debug' => true, 'kernel.container_class' => __CLASS__));

        $this->assertFalse($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
        $this->assertTrue($container->hasDefinition('serializer.mapping.class_metadata_factory'));

        $cache = $container->getDefinition('serializer.mapping.class_metadata_factory')->getArgument(1);
        $this->assertEquals(new Reference('foo'), $cache);
    }

    public function testSerializerMapping()
    {
        $container = $this->createContainerFromFile('serializer_mapping', array('kernel.bundles_metadata' => array('TestBundle' => array('namespace' => 'Symfony\\Bundle\\FrameworkBundle\\Tests', 'path' => __DIR__.'/Fixtures/TestBundle', 'parent' => null))));
        $configDir = __DIR__.'/Fixtures/TestBundle/Resources/config';
        $expectedLoaders = array(
            new Definition(AnnotationLoader::class, array(new Reference('annotation_reader'))),
            new Definition(XmlFileLoader::class, array($configDir.'/serialization.xml')),
            new Definition(YamlFileLoader::class, array($configDir.'/serialization.yml')),
            new Definition(XmlFileLoader::class, array($configDir.'/serializer_mapping/files/foo.xml')),
            new Definition(YamlFileLoader::class, array($configDir.'/serializer_mapping/files/foo.yml')),
            new Definition(YamlFileLoader::class, array($configDir.'/serializer_mapping/serialization.yml')),
            new Definition(YamlFileLoader::class, array($configDir.'/serializer_mapping/serialization.yaml')),
        );

        foreach ($expectedLoaders as $definition) {
            $definition->setPublic(false);
        }

        $loaders = $container->getDefinition('serializer.mapping.chain_loader')->getArgument(0);
        $this->assertEquals(sort($expectedLoaders), sort($loaders));
    }

    public function testAssetHelperWhenAssetsAreEnabled()
    {
        $container = $this->createContainerFromFile('full');
        $packages = $container->getDefinition('templating.helper.assets')->getArgument(0);

        $this->assertSame('assets.packages', (string) $packages);
    }

    public function testAssetHelperWhenTemplatesAreEnabledAndNoAssetsConfiguration()
    {
        $container = $this->createContainerFromFile('templating_no_assets');
        $packages = $container->getDefinition('templating.helper.assets')->getArgument(0);

        $this->assertSame('assets.packages', (string) $packages);
    }

    public function testAssetsHelperIsRemovedWhenPhpTemplatingEngineIsEnabledAndAssetsAreDisabled()
    {
        $container = $this->createContainerFromFile('templating_php_assets_disabled');

        $this->assertTrue(!$container->has('templating.helper.assets'), 'The templating.helper.assets helper service is removed when assets are disabled.');
    }

    public function testAssetHelperWhenAssetsAndTemplatesAreDisabled()
    {
        $container = $this->createContainerFromFile('default_config');

        $this->assertFalse($container->hasDefinition('templating.helper.assets'));
    }

    public function testSerializerServiceIsRegisteredWhenEnabled()
    {
        $container = $this->createContainerFromFile('serializer_enabled');

        $this->assertTrue($container->hasDefinition('serializer'));
    }

    public function testSerializerServiceIsNotRegisteredWhenDisabled()
    {
        $container = $this->createContainerFromFile('serializer_disabled');

        $this->assertFalse($container->hasDefinition('serializer'));
    }

    public function testPropertyInfoDisabled()
    {
        $container = $this->createContainerFromFile('default_config');
        $this->assertFalse($container->has('property_info'));
    }

    public function testPropertyInfoEnabled()
    {
        $container = $this->createContainerFromFile('property_info');
        $this->assertTrue($container->has('property_info'));
    }

    public function testEventDispatcherService()
    {
        $container = $this->createContainer(array('kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret'));
        $container->registerExtension(new FrameworkExtension());
        $this->loadFromFile($container, 'default_config');
        $container
            ->register('foo', \stdClass::class)
            ->setProperty('dispatcher', new Reference('event_dispatcher'));
        $container->compile();
        $this->assertInstanceOf(EventDispatcherInterface::class, $container->get('foo')->dispatcher);
    }

    public function testCacheDefaultRedisProvider()
    {
        $container = $this->createContainerFromFile('cache');

        $redisUrl = 'redis://localhost';
        $providerId = md5($redisUrl);

        $this->assertTrue($container->hasDefinition($providerId));

        $url = $container->getDefinition($providerId)->getArgument(0);

        $this->assertSame($redisUrl, $url);
    }

    public function testCacheDefaultRedisProviderWithEnvVar()
    {
        $container = $this->createContainerFromFile('cache_env_var');

        $redisUrl = 'redis://paas.com';
        $providerId = md5($redisUrl);

        $this->assertTrue($container->hasDefinition($providerId));

        $url = $container->getDefinition($providerId)->getArgument(0);

        $this->assertSame($redisUrl, $url);
    }

    public function testCachePoolServices()
    {
        $container = $this->createContainerFromFile('cache');

        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.foo', 'cache.adapter.apcu', 30);
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.bar', 'cache.adapter.doctrine', 5);
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.baz', 'cache.adapter.filesystem', 7);
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.foobar', 'cache.adapter.psr6', 10);
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.def', 'cache.app', 11);
    }

    protected function createContainer(array $data = array())
    {
        return new ContainerBuilder(new ParameterBag(array_merge(array(
            'kernel.bundles' => array('FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'),
            'kernel.bundles_metadata' => array('FrameworkBundle' => array('namespace' => 'Symfony\\Bundle\\FrameworkBundle', 'path' => __DIR__.'/../..', 'parent' => null)),
            'kernel.cache_dir' => __DIR__,
            'kernel.debug' => false,
            'kernel.environment' => 'test',
            'kernel.name' => 'kernel',
            'kernel.root_dir' => __DIR__,
            'kernel.container_class' => 'testContainer',
        ), $data)));
    }

    protected function createContainerFromFile($file, $data = array(), $resetCompilerPasses = true)
    {
        $cacheKey = md5(get_class($this).$file.serialize($data));
        if (isset(self::$containerCache[$cacheKey])) {
            return self::$containerCache[$cacheKey];
        }
        $container = $this->createContainer($data);
        $container->registerExtension(new FrameworkExtension());
        $this->loadFromFile($container, $file);

        if ($resetCompilerPasses) {
            $container->getCompilerPassConfig()->setOptimizationPasses(array());
            $container->getCompilerPassConfig()->setRemovingPasses(array());
        }
        $container->getCompilerPassConfig()->setBeforeRemovingPasses(array(new AddAnnotationsCachedReaderPass(), new AddConstraintValidatorsPass(), new TranslatorPass()));
        $container->compile();

        return self::$containerCache[$cacheKey] = $container;
    }

    protected function createContainerFromClosure($closure, $data = array())
    {
        $container = $this->createContainer($data);
        $container->registerExtension(new FrameworkExtension());
        $loader = new ClosureLoader($container);
        $loader->load($closure);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        return $container;
    }

    private function assertPathPackage(ContainerBuilder $container, ChildDefinition $package, $basePath, $version, $format)
    {
        $this->assertEquals('assets.path_package', $package->getParent());
        $this->assertEquals($basePath, $package->getArgument(0));
        $this->assertVersionStrategy($container, $package->getArgument(1), $version, $format);
    }

    private function assertUrlPackage(ContainerBuilder $container, ChildDefinition $package, $baseUrls, $version, $format)
    {
        $this->assertEquals('assets.url_package', $package->getParent());
        $this->assertEquals($baseUrls, $package->getArgument(0));
        $this->assertVersionStrategy($container, $package->getArgument(1), $version, $format);
    }

    private function assertVersionStrategy(ContainerBuilder $container, Reference $reference, $version, $format)
    {
        $versionStrategy = $container->getDefinition((string) $reference);
        if (null === $version) {
            $this->assertEquals('assets.empty_version_strategy', (string) $reference);
        } else {
            $this->assertEquals('assets.static_version_strategy', $versionStrategy->getParent());
            $this->assertEquals($version, $versionStrategy->getArgument(0));
            $this->assertEquals($format, $versionStrategy->getArgument(1));
        }
    }

    private function assertCachePoolServiceDefinitionIsCreated(ContainerBuilder $container, $id, $adapter, $defaultLifetime)
    {
        $this->assertTrue($container->has($id), sprintf('Service definition "%s" for cache pool of type "%s" is registered', $id, $adapter));

        $poolDefinition = $container->getDefinition($id);

        $this->assertInstanceOf(ChildDefinition::class, $poolDefinition, sprintf('Cache pool "%s" is based on an abstract cache pool.', $id));

        $this->assertTrue($poolDefinition->hasTag('cache.pool'), sprintf('Service definition "%s" is tagged with the "cache.pool" tag.', $id));
        $this->assertFalse($poolDefinition->isAbstract(), sprintf('Service definition "%s" is not abstract.', $id));

        $tag = $poolDefinition->getTag('cache.pool');
        $this->assertTrue(isset($tag[0]['default_lifetime']), 'The default lifetime is stored as an attribute of the "cache.pool" tag.');
        $this->assertSame($defaultLifetime, $tag[0]['default_lifetime'], 'The default lifetime is stored as an attribute of the "cache.pool" tag.');

        $parentDefinition = $poolDefinition;
        do {
            $parentId = $parentDefinition->getParent();
            $parentDefinition = $container->findDefinition($parentId);
        } while ($parentDefinition instanceof ChildDefinition);

        switch ($adapter) {
            case 'cache.adapter.apcu':
                $this->assertSame(ApcuAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.adapter.doctrine':
                $this->assertSame(DoctrineAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.app':
                if (ChainAdapter::class === $parentDefinition->getClass()) {
                    break;
                }
                // no break
            case 'cache.adapter.filesystem':
                $this->assertSame(FilesystemAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.adapter.psr6':
                $this->assertSame(ProxyAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.adapter.redis':
                $this->assertSame(RedisAdapter::class, $parentDefinition->getClass());
                break;
            default:
                $this->fail('Unresolved adapter: '.$adapter);
        }
    }
}
