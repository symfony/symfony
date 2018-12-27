<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader;
use Symfony\Bundle\FullStack;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\ResourceCheckerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Lock\StoreInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\Loader\AnnotationFileLoader;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Translation\Command\XliffLintCommand as BaseXliffLintCommand;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\Workflow;
use Symfony\Component\Yaml\Command\LintCommand as BaseYamlLintCommand;
use Symfony\Component\Yaml\Yaml;

/**
 * FrameworkExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jeremy Mikola <jmikola@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class FrameworkExtension extends Extension
{
    private $formConfigEnabled = false;
    private $translationConfigEnabled = false;
    private $sessionConfigEnabled = false;
    private $annotationsConfigEnabled = false;
    private $validatorConfigEnabled = false;

    /**
     * @var string|null
     */
    private $kernelRootHash;

    /**
     * Responds to the app.config configuration parameter.
     *
     * @throws LogicException
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));

        $loader->load('web.xml');
        $loader->load('services.xml');

        $container->getDefinition('kernel.class_cache.cache_warmer')->setPrivate(true);
        $container->getDefinition('uri_signer')->setPrivate(true);
        $container->getDefinition('config_cache_factory')->setPrivate(true);
        $container->getDefinition('response_listener')->setPrivate(true);
        $container->getDefinition('file_locator')->setPrivate(true);
        $container->getDefinition('streamed_response_listener')->setPrivate(true);
        $container->getDefinition('locale_listener')->setPrivate(true);
        $container->getDefinition('validate_request_listener')->setPrivate(true);

        // forward compatibility with Symfony 4.0 where the ContainerAwareEventDispatcher class is removed
        if (!class_exists(ContainerAwareEventDispatcher::class)) {
            $definition = $container->getDefinition('event_dispatcher');
            $definition->setClass(EventDispatcher::class);
            $definition->setArguments(array());
        }

        if (\PHP_VERSION_ID < 70000) {
            $definition = $container->getDefinition('kernel.class_cache.cache_warmer');
            $definition->addTag('kernel.cache_warmer');
            // Ignore deprecation for PHP versions below 7.0
            $definition->setDeprecated(false);
        }

        $loader->load('fragment_renderer.xml');

        $container->getDefinition('fragment.handler')->setPrivate(true);
        $container->getDefinition('fragment.renderer.inline')->setPrivate(true);
        $container->getDefinition('fragment.renderer.hinclude')->setPrivate(true);
        $container->getDefinition('fragment.renderer.esi')->setPrivate(true);
        $container->getDefinition('fragment.renderer.ssi')->setPrivate(true);

        if (class_exists(Application::class)) {
            $loader->load('console.xml');

            if (!class_exists(BaseXliffLintCommand::class)) {
                $container->removeDefinition('console.command.xliff_lint');
            }
            if (!class_exists(BaseYamlLintCommand::class)) {
                $container->removeDefinition('console.command.yaml_lint');
            }
        }

        // Load Cache configuration first as it is used by other components
        $loader->load('cache.xml');

        $container->getDefinition('cache.adapter.system')->setPrivate(true);
        $container->getDefinition('cache.adapter.apcu')->setPrivate(true);
        $container->getDefinition('cache.adapter.doctrine')->setPrivate(true);
        $container->getDefinition('cache.adapter.filesystem')->setPrivate(true);
        $container->getDefinition('cache.adapter.psr6')->setPrivate(true);
        $container->getDefinition('cache.adapter.redis')->setPrivate(true);
        $container->getDefinition('cache.adapter.memcached')->setPrivate(true);
        $container->getDefinition('cache.default_clearer')->setPrivate(true);

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $this->annotationsConfigEnabled = $this->isConfigEnabled($container, $config['annotations']);
        $this->translationConfigEnabled = $this->isConfigEnabled($container, $config['translator']);

        // A translator must always be registered (as support is included by
        // default in the Form and Validator component). If disabled, an identity
        // translator will be used and everything will still work as expected.
        if ($this->isConfigEnabled($container, $config['translator']) || $this->isConfigEnabled($container, $config['form']) || $this->isConfigEnabled($container, $config['validation'])) {
            if (!class_exists('Symfony\Component\Translation\Translator') && $this->isConfigEnabled($container, $config['translator'])) {
                throw new LogicException('Translation support cannot be enabled as the Translation component is not installed. Try running "composer require symfony/translation".');
            }

            if (class_exists(Translator::class)) {
                $loader->load('identity_translator.xml');
            }
        }

        if (isset($config['secret'])) {
            $container->setParameter('kernel.secret', $config['secret']);
        }

        $container->setParameter('kernel.http_method_override', $config['http_method_override']);
        $container->setParameter('kernel.trusted_hosts', $config['trusted_hosts']);
        if ($config['trusted_proxies']) {
            $container->setParameter('kernel.trusted_proxies', $config['trusted_proxies']);
        }
        $container->setParameter('kernel.default_locale', $config['default_locale']);

        if (!$container->hasParameter('debug.file_link_format')) {
            if (!$container->hasParameter('templating.helper.code.file_link_format')) {
                $links = array(
                    'textmate' => 'txmt://open?url=file://%%f&line=%%l',
                    'macvim' => 'mvim://open?url=file://%%f&line=%%l',
                    'emacs' => 'emacs://open?url=file://%%f&line=%%l',
                    'sublime' => 'subl://open?url=file://%%f&line=%%l',
                    'phpstorm' => 'phpstorm://open?file=%%f&line=%%l',
                );
                $ide = $config['ide'];

                $container->setParameter('templating.helper.code.file_link_format', str_replace('%', '%%', ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format')) ?: (isset($links[$ide]) ? $links[$ide] : $ide));
            }
            $container->setParameter('debug.file_link_format', '%templating.helper.code.file_link_format%');
        }

        if (!empty($config['test'])) {
            $loader->load('test.xml');

            $container->getDefinition('test.client.history')->setPrivate(true);
            $container->getDefinition('test.client.cookiejar')->setPrivate(true);
            $container->getDefinition('test.session.listener')->setPrivate(true);

            if (!class_exists(Client::class)) {
                $container->removeDefinition('test.client');
            }
        }

        if ($this->isConfigEnabled($container, $config['session'])) {
            $this->sessionConfigEnabled = true;
            $this->registerSessionConfiguration($config['session'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['request'])) {
            $this->registerRequestConfiguration($config['request'], $container, $loader);
        }

        if (null === $config['csrf_protection']['enabled']) {
            $config['csrf_protection']['enabled'] = $this->sessionConfigEnabled && !class_exists(FullStack::class) && interface_exists(CsrfTokenManagerInterface::class);
        }
        $this->registerSecurityCsrfConfiguration($config['csrf_protection'], $container, $loader);

        if ($this->isConfigEnabled($container, $config['form'])) {
            if (!class_exists('Symfony\Component\Form\Form')) {
                throw new LogicException('Form support cannot be enabled as the Form component is not installed. Try running "composer require symfony/form".');
            }

            $this->formConfigEnabled = true;
            $this->registerFormConfiguration($config, $container, $loader);

            if (class_exists('Symfony\Component\Validator\Validation')) {
                $config['validation']['enabled'] = true;
            } else {
                $container->setParameter('validator.translation_domain', 'validators');

                $container->removeDefinition('form.type_extension.form.validator');
                $container->removeDefinition('form.type_guesser.validator');
            }
        } else {
            $container->removeDefinition('console.command.form_debug');
        }

        if ($this->isConfigEnabled($container, $config['assets'])) {
            if (!class_exists('Symfony\Component\Asset\Package')) {
                throw new LogicException('Asset support cannot be enabled as the Asset component is not installed. Try running "composer require symfony/asset".');
            }

            $this->registerAssetsConfiguration($config['assets'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['templating'])) {
            if (!class_exists('Symfony\Component\Templating\PhpEngine')) {
                throw new LogicException('Templating support cannot be enabled as the Templating component is not installed. Try running "composer require symfony/templating".');
            }

            $this->registerTemplatingConfiguration($config['templating'], $container, $loader);
        }

        $this->registerValidationConfiguration($config['validation'], $container, $loader);
        $this->registerEsiConfiguration($config['esi'], $container, $loader);
        $this->registerSsiConfiguration($config['ssi'], $container, $loader);
        $this->registerFragmentsConfiguration($config['fragments'], $container, $loader);
        $this->registerTranslatorConfiguration($config['translator'], $container, $loader);
        $this->registerProfilerConfiguration($config['profiler'], $container, $loader);
        $this->registerCacheConfiguration($config['cache'], $container);
        $this->registerWorkflowConfiguration($config['workflows'], $container, $loader);
        $this->registerDebugConfiguration($config['php_errors'], $container, $loader);
        $this->registerRouterConfiguration($config['router'], $container, $loader);
        $this->registerAnnotationsConfiguration($config['annotations'], $container, $loader);
        $this->registerPropertyAccessConfiguration($config['property_access'], $container, $loader);

        if ($this->isConfigEnabled($container, $config['serializer'])) {
            if (!class_exists('Symfony\Component\Serializer\Serializer')) {
                throw new LogicException('Serializer support cannot be enabled as the Serializer component is not installed. Try running "composer require symfony/serializer-pack".');
            }

            $this->registerSerializerConfiguration($config['serializer'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['property_info'])) {
            $this->registerPropertyInfoConfiguration($container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['lock'])) {
            $this->registerLockConfiguration($config['lock'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['web_link'])) {
            if (!class_exists(HttpHeaderSerializer::class)) {
                throw new LogicException('WebLink support cannot be enabled as the WebLink component is not installed. Try running "composer require symfony/weblink".');
            }

            $loader->load('web_link.xml');
        }

        $this->addAnnotatedClassesToCompile(array(
            '**\\Controller\\',
            '**\\Entity\\',

            // Added explicitly so that we don't rely on the class map being dumped to make it work
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\Controller',
        ));

        $container->registerForAutoconfiguration(Command::class)
            ->addTag('console.command');
        $container->registerForAutoconfiguration(ResourceCheckerInterface::class)
            ->addTag('config_cache.resource_checker');
        $container->registerForAutoconfiguration(EnvVarProcessorInterface::class)
            ->addTag('container.env_var_processor');
        $container->registerForAutoconfiguration(ServiceSubscriberInterface::class)
            ->addTag('container.service_subscriber');
        $container->registerForAutoconfiguration(ArgumentValueResolverInterface::class)
            ->addTag('controller.argument_value_resolver');
        $container->registerForAutoconfiguration(AbstractController::class)
            ->addTag('controller.service_arguments');
        $container->registerForAutoconfiguration(Controller::class)
            ->addTag('controller.service_arguments');
        $container->registerForAutoconfiguration(DataCollectorInterface::class)
            ->addTag('data_collector');
        $container->registerForAutoconfiguration(FormTypeInterface::class)
            ->addTag('form.type');
        $container->registerForAutoconfiguration(FormTypeGuesserInterface::class)
            ->addTag('form.type_guesser');
        $container->registerForAutoconfiguration(CacheClearerInterface::class)
            ->addTag('kernel.cache_clearer');
        $container->registerForAutoconfiguration(CacheWarmerInterface::class)
            ->addTag('kernel.cache_warmer');
        $container->registerForAutoconfiguration(EventSubscriberInterface::class)
            ->addTag('kernel.event_subscriber');
        $container->registerForAutoconfiguration(ResettableInterface::class)
            ->addTag('kernel.reset', array('method' => 'reset'));
        $container->registerForAutoconfiguration(PropertyListExtractorInterface::class)
            ->addTag('property_info.list_extractor');
        $container->registerForAutoconfiguration(PropertyTypeExtractorInterface::class)
            ->addTag('property_info.type_extractor');
        $container->registerForAutoconfiguration(PropertyDescriptionExtractorInterface::class)
            ->addTag('property_info.description_extractor');
        $container->registerForAutoconfiguration(PropertyAccessExtractorInterface::class)
            ->addTag('property_info.access_extractor');
        $container->registerForAutoconfiguration(EncoderInterface::class)
            ->addTag('serializer.encoder');
        $container->registerForAutoconfiguration(DecoderInterface::class)
            ->addTag('serializer.encoder');
        $container->registerForAutoconfiguration(NormalizerInterface::class)
            ->addTag('serializer.normalizer');
        $container->registerForAutoconfiguration(DenormalizerInterface::class)
            ->addTag('serializer.normalizer');
        $container->registerForAutoconfiguration(ConstraintValidatorInterface::class)
            ->addTag('validator.constraint_validator');
        $container->registerForAutoconfiguration(ObjectInitializerInterface::class)
            ->addTag('validator.initializer');

        if (!$container->getParameter('kernel.debug')) {
            // remove tagged iterator argument for resource checkers
            $container->getDefinition('config_cache_factory')->setArguments(array());
        }

        if (\PHP_VERSION_ID < 70000) {
            $this->addClassesToCompile(array(
                'Symfony\\Component\\Config\\ConfigCache',
                'Symfony\\Component\\Config\\FileLocator',

                'Symfony\\Component\\Debug\\ErrorHandler',

                'Symfony\\Component\\DependencyInjection\\ContainerAwareInterface',
                'Symfony\\Component\\DependencyInjection\\Container',

                'Symfony\\Component\\EventDispatcher\\Event',
                'Symfony\\Component\\EventDispatcher\\ContainerAwareEventDispatcher',

                'Symfony\\Component\\HttpKernel\\EventListener\\ResponseListener',
                'Symfony\\Component\\HttpKernel\\EventListener\\RouterListener',
                'Symfony\\Component\\HttpKernel\\Bundle\\Bundle',
                'Symfony\\Component\\HttpKernel\\Controller\\ControllerResolver',
                'Symfony\\Component\\HttpKernel\\Controller\\ArgumentResolver',
                'Symfony\\Component\\HttpKernel\\ControllerMetadata\\ArgumentMetadata',
                'Symfony\\Component\\HttpKernel\\ControllerMetadata\\ArgumentMetadataFactory',
                'Symfony\\Component\\HttpKernel\\Event\\KernelEvent',
                'Symfony\\Component\\HttpKernel\\Event\\FilterControllerEvent',
                'Symfony\\Component\\HttpKernel\\Event\\FilterResponseEvent',
                'Symfony\\Component\\HttpKernel\\Event\\GetResponseEvent',
                'Symfony\\Component\\HttpKernel\\Event\\GetResponseForControllerResultEvent',
                'Symfony\\Component\\HttpKernel\\Event\\GetResponseForExceptionEvent',
                'Symfony\\Component\\HttpKernel\\HttpKernel',
                'Symfony\\Component\\HttpKernel\\KernelEvents',
                'Symfony\\Component\\HttpKernel\\Config\\FileLocator',

                'Symfony\\Bundle\\FrameworkBundle\\Controller\\ControllerNameParser',
                'Symfony\\Bundle\\FrameworkBundle\\Controller\\ControllerResolver',

                // Cannot be included because annotations will parse the big compiled class file
                // 'Symfony\\Bundle\\FrameworkBundle\\Controller\\Controller',

                // cannot be included as commands are discovered based on the path to this class via Reflection
                // 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle',
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }

    private function registerFormConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('form.xml');

        $container->getDefinition('form.resolved_type_factory')->setPrivate(true);
        $container->getDefinition('form.registry')->setPrivate(true);
        $container->getDefinition('form.type_guesser.validator')->setPrivate(true);
        $container->getDefinition('form.type.form')->setPrivate(true);
        $container->getDefinition('form.type.choice')->setPrivate(true);
        $container->getDefinition('form.type_extension.form.http_foundation')->setPrivate(true);
        $container->getDefinition('form.type_extension.form.validator')->setPrivate(true);
        $container->getDefinition('form.type_extension.repeated.validator')->setPrivate(true);
        $container->getDefinition('form.type_extension.submit.validator')->setPrivate(true);
        $container->getDefinition('form.type_extension.upload.validator')->setPrivate(true);
        $container->getDefinition('deprecated.form.registry')->setPrivate(true);

        if (null === $config['form']['csrf_protection']['enabled']) {
            $config['form']['csrf_protection']['enabled'] = $config['csrf_protection']['enabled'];
        }

        if ($this->isConfigEnabled($container, $config['form']['csrf_protection'])) {
            $loader->load('form_csrf.xml');

            $container->getDefinition('form.type_extension.csrf')->setPrivate(true);
            $container->getDefinition('deprecated.form.registry.csrf')->setPrivate(true);

            $container->setParameter('form.type_extension.csrf.enabled', true);
            $container->setParameter('form.type_extension.csrf.field_name', $config['form']['csrf_protection']['field_name']);
        } else {
            $container->setParameter('form.type_extension.csrf.enabled', false);
        }

        if (!class_exists(Translator::class)) {
            $container->removeDefinition('form.type_extension.upload.validator');
        }
    }

    private function registerEsiConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            $container->removeDefinition('fragment.renderer.esi');

            return;
        }

        $loader->load('esi.xml');

        $container->getDefinition('esi')->setPrivate(true);
        $container->getDefinition('esi_listener')->setPrivate(true);
    }

    private function registerSsiConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            $container->removeDefinition('fragment.renderer.ssi');

            return;
        }

        $loader->load('ssi.xml');

        $container->getDefinition('ssi')->setPrivate(true);
        $container->getDefinition('ssi_listener')->setPrivate(true);
    }

    private function registerFragmentsConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            $container->removeDefinition('fragment.renderer.hinclude');

            return;
        }

        $loader->load('fragment_listener.xml');
        $container->setParameter('fragment.path', $config['path']);
    }

    private function registerProfilerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            // this is needed for the WebProfiler to work even if the profiler is disabled
            $container->setParameter('data_collector.templates', array());

            return;
        }

        $loader->load('profiling.xml');
        $loader->load('collectors.xml');
        $loader->load('cache_debug.xml');

        $container->getDefinition('data_collector.request')->setPrivate(true);
        $container->getDefinition('data_collector.router')->setPrivate(true);
        $container->getDefinition('profiler_listener')->setPrivate(true);

        if ($this->formConfigEnabled) {
            $loader->load('form_debug.xml');

            $container->getDefinition('form.resolved_type_factory')->setPrivate(true);
            $container->getDefinition('data_collector.form.extractor')->setPrivate(true);
            $container->getDefinition('data_collector.form')->setPrivate(true);
        }

        if ($this->validatorConfigEnabled) {
            $loader->load('validator_debug.xml');
        }

        if ($this->translationConfigEnabled) {
            $loader->load('translation_debug.xml');

            $container->getDefinition('data_collector.translation')->setPrivate(true);

            $container->getDefinition('translator.data_collector')->setDecoratedService('translator');
        }

        $container->setParameter('profiler_listener.only_exceptions', $config['only_exceptions']);
        $container->setParameter('profiler_listener.only_master_requests', $config['only_master_requests']);

        // Choose storage class based on the DSN
        list($class) = explode(':', $config['dsn'], 2);
        if ('file' !== $class) {
            throw new \LogicException(sprintf('Driver "%s" is not supported for the profiler.', $class));
        }

        $container->setParameter('profiler.storage.dsn', $config['dsn']);

        if ($this->isConfigEnabled($container, $config['matcher'])) {
            if (isset($config['matcher']['service'])) {
                $container->setAlias('profiler.request_matcher', $config['matcher']['service'])->setPrivate(true);
            } elseif (isset($config['matcher']['ip']) || isset($config['matcher']['path']) || isset($config['matcher']['ips'])) {
                $definition = $container->register('profiler.request_matcher', 'Symfony\\Component\\HttpFoundation\\RequestMatcher');
                $definition->setPublic(false);

                if (isset($config['matcher']['ip'])) {
                    $definition->addMethodCall('matchIp', array($config['matcher']['ip']));
                }

                if (isset($config['matcher']['ips'])) {
                    $definition->addMethodCall('matchIps', array($config['matcher']['ips']));
                }

                if (isset($config['matcher']['path'])) {
                    $definition->addMethodCall('matchPath', array($config['matcher']['path']));
                }
            }
        }

        $container->getDefinition('profiler')
            ->addArgument($config['collect'])
            ->addTag('kernel.reset', array('method' => 'reset'));
    }

    private function registerWorkflowConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$config['enabled']) {
            $container->removeDefinition('console.command.workflow_dump');

            return;
        }

        if (!class_exists(Workflow\Workflow::class)) {
            throw new LogicException('Workflow support cannot be enabled as the Workflow component is not installed. Try running "composer require symfony/workflow".');
        }

        $loader->load('workflow.xml');

        $container->getDefinition('workflow.marking_store.multiple_state')->setPrivate(true);
        $container->getDefinition('workflow.marking_store.single_state')->setPrivate(true);
        $container->getDefinition('workflow.registry')->setPrivate(true);

        $registryDefinition = $container->getDefinition('workflow.registry');

        foreach ($config['workflows'] as $name => $workflow) {
            if (!array_key_exists('type', $workflow)) {
                $workflow['type'] = 'workflow';
                @trigger_error(sprintf('The "type" option of the "framework.workflows.%s" configuration entry must be defined since Symfony 3.3. The default value will be "state_machine" in Symfony 4.0.', $name), E_USER_DEPRECATED);
            }
            $type = $workflow['type'];
            $workflowId = sprintf('%s.%s', $type, $name);

            // Create transitions
            $transitions = array();
            $guardsConfiguration = array();
            // Global transition counter per workflow
            $transitionCounter = 0;
            foreach ($workflow['transitions'] as $transition) {
                if ('workflow' === $type) {
                    $transitionDefinition = new Definition(Workflow\Transition::class, array($transition['name'], $transition['from'], $transition['to']));
                    $transitionDefinition->setPublic(false);
                    $transitionId = sprintf('%s.transition.%s', $workflowId, $transitionCounter++);
                    $container->setDefinition($transitionId, $transitionDefinition);
                    $transitions[] = new Reference($transitionId);
                    if (isset($transition['guard'])) {
                        $configuration = new Definition(Workflow\EventListener\GuardExpression::class);
                        $configuration->addArgument(new Reference($transitionId));
                        $configuration->addArgument($transition['guard']);
                        $configuration->setPublic(false);
                        $eventName = sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                        $guardsConfiguration[$eventName][] = $configuration;
                    }
                } elseif ('state_machine' === $type) {
                    foreach ($transition['from'] as $from) {
                        foreach ($transition['to'] as $to) {
                            $transitionDefinition = new Definition(Workflow\Transition::class, array($transition['name'], $from, $to));
                            $transitionDefinition->setPublic(false);
                            $transitionId = sprintf('%s.transition.%s', $workflowId, $transitionCounter++);
                            $container->setDefinition($transitionId, $transitionDefinition);
                            $transitions[] = new Reference($transitionId);
                            if (isset($transition['guard'])) {
                                $configuration = new Definition(Workflow\EventListener\GuardExpression::class);
                                $configuration->addArgument(new Reference($transitionId));
                                $configuration->addArgument($transition['guard']);
                                $configuration->setPublic(false);
                                $eventName = sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                                $guardsConfiguration[$eventName][] = $configuration;
                            }
                        }
                    }
                }
            }

            // Create a Definition
            $definitionDefinition = new Definition(Workflow\Definition::class);
            $definitionDefinition->setPublic(false);
            $definitionDefinition->addArgument($workflow['places']);
            $definitionDefinition->addArgument($transitions);
            $definitionDefinition->addTag('workflow.definition', array(
                'name' => $name,
                'type' => $type,
                'marking_store' => isset($workflow['marking_store']['type']) ? $workflow['marking_store']['type'] : null,
            ));
            if (isset($workflow['initial_place'])) {
                $definitionDefinition->addArgument($workflow['initial_place']);
            }

            // Create MarkingStore
            if (isset($workflow['marking_store']['type'])) {
                $markingStoreDefinition = new ChildDefinition('workflow.marking_store.'.$workflow['marking_store']['type']);
                foreach ($workflow['marking_store']['arguments'] as $argument) {
                    $markingStoreDefinition->addArgument($argument);
                }
            } elseif (isset($workflow['marking_store']['service'])) {
                $markingStoreDefinition = new Reference($workflow['marking_store']['service']);
            }

            // Create Workflow
            $workflowDefinition = new ChildDefinition(sprintf('%s.abstract', $type));
            $workflowDefinition->replaceArgument(0, new Reference(sprintf('%s.definition', $workflowId)));
            if (isset($markingStoreDefinition)) {
                $workflowDefinition->replaceArgument(1, $markingStoreDefinition);
            }
            $workflowDefinition->replaceArgument(3, $name);

            // Store to container
            $container->setDefinition($workflowId, $workflowDefinition);
            $container->setDefinition(sprintf('%s.definition', $workflowId), $definitionDefinition);

            // Add workflow to Registry
            if ($workflow['supports']) {
                foreach ($workflow['supports'] as $supportedClassName) {
                    $strategyDefinition = new Definition(Workflow\SupportStrategy\ClassInstanceSupportStrategy::class, array($supportedClassName));
                    $strategyDefinition->setPublic(false);
                    $registryDefinition->addMethodCall('add', array(new Reference($workflowId), $strategyDefinition));
                }
            } elseif (isset($workflow['support_strategy'])) {
                $registryDefinition->addMethodCall('add', array(new Reference($workflowId), new Reference($workflow['support_strategy'])));
            }

            // Enable the AuditTrail
            if ($workflow['audit_trail']['enabled']) {
                $listener = new Definition(Workflow\EventListener\AuditTrailListener::class);
                $listener->setPrivate(true);
                $listener->addTag('monolog.logger', array('channel' => 'workflow'));
                $listener->addTag('kernel.event_listener', array('event' => sprintf('workflow.%s.leave', $name), 'method' => 'onLeave'));
                $listener->addTag('kernel.event_listener', array('event' => sprintf('workflow.%s.transition', $name), 'method' => 'onTransition'));
                $listener->addTag('kernel.event_listener', array('event' => sprintf('workflow.%s.enter', $name), 'method' => 'onEnter'));
                $listener->addArgument(new Reference('logger'));
                $container->setDefinition(sprintf('%s.listener.audit_trail', $workflowId), $listener);
            }

            // Add Guard Listener
            if ($guardsConfiguration) {
                if (!class_exists(ExpressionLanguage::class)) {
                    throw new LogicException('Cannot guard workflows as the ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
                }

                if (!class_exists(Security::class)) {
                    throw new LogicException('Cannot guard workflows as the Security component is not installed. Try running "composer require symfony/security".');
                }

                $guard = new Definition(Workflow\EventListener\GuardListener::class);
                $guard->setPrivate(true);

                $guard->setArguments(array(
                    $guardsConfiguration,
                    new Reference('workflow.security.expression_language'),
                    new Reference('security.token_storage'),
                    new Reference('security.authorization_checker'),
                    new Reference('security.authentication.trust_resolver'),
                    new Reference('security.role_hierarchy'),
                    new Reference('validator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ));
                foreach ($guardsConfiguration as $eventName => $config) {
                    $guard->addTag('kernel.event_listener', array('event' => $eventName, 'method' => 'onTransition'));
                }

                $container->setDefinition(sprintf('%s.listener.guard', $workflowId), $guard);
                $container->setParameter('workflow.has_guard_listeners', true);
            }
        }
    }

    private function registerDebugConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('debug_prod.xml');

        $container->getDefinition('debug.debug_handlers_listener')->setPrivate(true);

        if (class_exists(Stopwatch::class)) {
            $container->register('debug.stopwatch', Stopwatch::class)
                ->addArgument(true)
                ->setPrivate(true)
                ->addTag('kernel.reset', array('method' => 'reset'));
            $container->setAlias(Stopwatch::class, new Alias('debug.stopwatch', false));
        }

        $debug = $container->getParameter('kernel.debug');

        if ($debug) {
            $container->setParameter('debug.container.dump', '%kernel.cache_dir%/%kernel.container_class%.xml');
        }

        if ($debug && class_exists(Stopwatch::class)) {
            $loader->load('debug.xml');
            $container->getDefinition('debug.event_dispatcher')->setPrivate(true);
            $container->getDefinition('debug.controller_resolver')->setPrivate(true);
            $container->getDefinition('debug.argument_resolver')->setPrivate(true);
        }

        $definition = $container->findDefinition('debug.debug_handlers_listener');

        if (!$config['log']) {
            $definition->replaceArgument(1, null);
        }

        if (!$config['throw']) {
            $container->setParameter('debug.error_handler.throw_at', 0);
        }

        $definition->replaceArgument(4, $debug);
        $definition->replaceArgument(6, $debug);

        if ($debug && class_exists(DebugProcessor::class)) {
            $definition = new Definition(DebugProcessor::class);
            $definition->setPublic(false);
            $container->setDefinition('debug.log_processor', $definition);
        }
    }

    private function registerRouterConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            $container->removeDefinition('console.command.router_debug');
            $container->removeDefinition('console.command.router_match');

            return;
        }

        $loader->load('routing.xml');

        $container->getDefinition('router_listener')->setPrivate(true);

        $container->setParameter('router.resource', $config['resource']);
        $container->setParameter('router.cache_class_prefix', $container->getParameter('kernel.container_class'));
        $router = $container->findDefinition('router.default');
        $argument = $router->getArgument(2);
        $argument['strict_requirements'] = $config['strict_requirements'];
        if (isset($config['type'])) {
            $argument['resource_type'] = $config['type'];
        }
        $router->replaceArgument(2, $argument);

        $container->setParameter('request_listener.http_port', $config['http_port']);
        $container->setParameter('request_listener.https_port', $config['https_port']);

        if (\PHP_VERSION_ID < 70000) {
            $this->addClassesToCompile(array(
                'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
                'Symfony\\Component\\Routing\\RequestContext',
                'Symfony\\Component\\Routing\\Router',
                'Symfony\\Bundle\\FrameworkBundle\\Routing\\RedirectableUrlMatcher',
                $container->findDefinition('router.default')->getClass(),
            ));
        }

        if ($this->annotationsConfigEnabled) {
            $container->register('routing.loader.annotation', AnnotatedRouteControllerLoader::class)
                ->setPublic(false)
                ->addTag('routing.loader', array('priority' => -10))
                ->addArgument(new Reference('annotation_reader'));

            $container->register('routing.loader.annotation.directory', AnnotationDirectoryLoader::class)
                ->setPublic(false)
                ->addTag('routing.loader', array('priority' => -10))
                ->setArguments(array(
                    new Reference('file_locator'),
                    new Reference('routing.loader.annotation'),
                ));

            $container->register('routing.loader.annotation.file', AnnotationFileLoader::class)
                ->setPublic(false)
                ->addTag('routing.loader', array('priority' => -10))
                ->setArguments(array(
                    new Reference('file_locator'),
                    new Reference('routing.loader.annotation'),
                ));
        }
    }

    private function registerSessionConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('session.xml');

        $container->getDefinition('session.storage.native')->setPrivate(true);
        $container->getDefinition('session.storage.php_bridge')->setPrivate(true);
        $container->getDefinition('session_listener')->setPrivate(true);
        $container->getDefinition('session.save_listener')->setPrivate(true);
        $container->getAlias('session.storage.filesystem')->setPrivate(true);

        // session storage
        $container->setAlias('session.storage', $config['storage_id'])->setPrivate(true);
        $options = array('cache_limiter' => '0');
        foreach (array('name', 'cookie_lifetime', 'cookie_path', 'cookie_domain', 'cookie_secure', 'cookie_httponly', 'use_cookies', 'gc_maxlifetime', 'gc_probability', 'gc_divisor', 'use_strict_mode') as $key) {
            if (isset($config[$key])) {
                $options[$key] = $config[$key];
            }
        }

        $container->setParameter('session.storage.options', $options);

        // session handler (the internal callback registered with PHP session management)
        if (null === $config['handler_id']) {
            // Set the handler class to be null
            $container->getDefinition('session.storage.native')->replaceArgument(1, null);
            $container->getDefinition('session.storage.php_bridge')->replaceArgument(0, null);
        } else {
            $container->setAlias('session.handler', $config['handler_id'])->setPrivate(true);
        }

        $container->setParameter('session.save_path', $config['save_path']);

        if (\PHP_VERSION_ID < 70000) {
            $this->addClassesToCompile(array(
                'Symfony\\Component\\HttpKernel\\EventListener\\SessionListener',
                'Symfony\\Component\\HttpFoundation\\Session\\Storage\\NativeSessionStorage',
                'Symfony\\Component\\HttpFoundation\\Session\\Storage\\PhpBridgeSessionStorage',
                'Symfony\\Component\\HttpFoundation\\Session\\Storage\\Handler\\NativeFileSessionHandler',
                'Symfony\\Component\\HttpFoundation\\Session\\Storage\\Proxy\\AbstractProxy',
                'Symfony\\Component\\HttpFoundation\\Session\\Storage\\Proxy\\SessionHandlerProxy',
                $container->getDefinition('session')->getClass(),
            ));

            if ($container->hasDefinition($config['storage_id'])) {
                $this->addClassesToCompile(array(
                    $container->findDefinition('session.storage')->getClass(),
                ));
            }
        }

        $container->setParameter('session.metadata.update_threshold', $config['metadata_update_threshold']);
    }

    private function registerRequestConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if ($config['formats']) {
            $loader->load('request.xml');

            $listener = $container->getDefinition('request.add_request_formats_listener');
            $listener->setPrivate(true);
            $listener->replaceArgument(0, $config['formats']);
        }
    }

    private function registerTemplatingConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('templating.xml');

        $container->getDefinition('templating.name_parser')->setPrivate(true);
        $container->getDefinition('templating.filename_parser')->setPrivate(true);

        $container->setParameter('fragment.renderer.hinclude.global_template', $config['hinclude_default_template']);

        if ($container->getParameter('kernel.debug')) {
            $logger = new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);

            $container->getDefinition('templating.loader.cache')
                ->addTag('monolog.logger', array('channel' => 'templating'))
                ->addMethodCall('setLogger', array($logger));
            $container->getDefinition('templating.loader.chain')
                ->addTag('monolog.logger', array('channel' => 'templating'))
                ->addMethodCall('setLogger', array($logger));
        }

        if (!empty($config['loaders'])) {
            $loaders = array_map(function ($loader) { return new Reference($loader); }, $config['loaders']);

            // Use a delegation unless only a single loader was registered
            if (1 === \count($loaders)) {
                $container->setAlias('templating.loader', (string) reset($loaders))->setPrivate(true);
            } else {
                $container->getDefinition('templating.loader.chain')->addArgument($loaders);
                $container->setAlias('templating.loader', 'templating.loader.chain')->setPrivate(true);
            }
        }

        $container->setParameter('templating.loader.cache.path', null);
        if (isset($config['cache'])) {
            // Wrap the existing loader with cache (must happen after loaders are registered)
            $container->setDefinition('templating.loader.wrapped', $container->findDefinition('templating.loader'));
            $loaderCache = $container->getDefinition('templating.loader.cache');
            $container->setParameter('templating.loader.cache.path', $config['cache']);

            $container->setDefinition('templating.loader', $loaderCache);
        }

        if (\PHP_VERSION_ID < 70000) {
            $this->addClassesToCompile(array(
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\GlobalVariables',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\TemplateReference',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\TemplateNameParser',
                $container->findDefinition('templating.locator')->getClass(),
            ));
        }

        $container->setParameter('templating.engines', $config['engines']);
        $engines = array_map(function ($engine) { return new Reference('templating.engine.'.$engine); }, $config['engines']);

        // Use a delegation unless only a single engine was registered
        if (1 === \count($engines)) {
            $container->setAlias('templating', (string) reset($engines))->setPublic(true);
        } else {
            $templateEngineDefinition = $container->getDefinition('templating.engine.delegating');
            foreach ($engines as $engine) {
                $templateEngineDefinition->addMethodCall('addEngine', array($engine));
            }
            $container->setAlias('templating', 'templating.engine.delegating')->setPublic(true);
        }

        $container->getDefinition('fragment.renderer.hinclude')
            ->addTag('kernel.fragment_renderer', array('alias' => 'hinclude'))
            ->replaceArgument(0, new Reference('templating'))
        ;

        // configure the PHP engine if needed
        if (\in_array('php', $config['engines'], true)) {
            $loader->load('templating_php.xml');

            $container->getDefinition('templating.helper.slots')->setPrivate(true);
            $container->getDefinition('templating.helper.request')->setPrivate(true);
            $container->getDefinition('templating.helper.session')->setPrivate(true);
            $container->getDefinition('templating.helper.router')->setPrivate(true);
            $container->getDefinition('templating.helper.assets')->setPrivate(true);
            $container->getDefinition('templating.helper.actions')->setPrivate(true);
            $container->getDefinition('templating.helper.code')->setPrivate(true);
            $container->getDefinition('templating.helper.translator')->setPrivate(true);
            $container->getDefinition('templating.helper.form')->setPrivate(true);
            $container->getDefinition('templating.helper.stopwatch')->setPrivate(true);
            $container->getDefinition('templating.globals')->setPrivate(true);

            $container->setParameter('templating.helper.form.resources', $config['form']['resources']);

            if ($container->getParameter('kernel.debug') && class_exists(Stopwatch::class)) {
                $loader->load('templating_debug.xml');

                $container->setDefinition('templating.engine.php', $container->findDefinition('debug.templating.engine.php'));
                $container->setAlias('debug.templating.engine.php', 'templating.engine.php')->setPrivate(true);
            }

            if (\PHP_VERSION_ID < 70000) {
                $this->addClassesToCompile(array(
                    'Symfony\\Component\\Templating\\Storage\\FileStorage',
                    'Symfony\\Bundle\\FrameworkBundle\\Templating\\PhpEngine',
                    'Symfony\\Bundle\\FrameworkBundle\\Templating\\Loader\\FilesystemLoader',
                ));
            }

            if ($container->has('assets.packages')) {
                $container->getDefinition('templating.helper.assets')->replaceArgument(0, new Reference('assets.packages'));
            } else {
                $container->removeDefinition('templating.helper.assets');
            }

            if (!$this->translationConfigEnabled) {
                $container->removeDefinition('templating.helper.translator');
            }
        }
    }

    private function registerAssetsConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('assets.xml');

        $container->getDefinition('assets.packages')->setPrivate(true);
        $container->getDefinition('assets.context')->setPrivate(true);
        $container->getDefinition('assets.path_package')->setPrivate(true);
        $container->getDefinition('assets.url_package')->setPrivate(true);
        $container->getDefinition('assets.static_version_strategy')->setPrivate(true);

        $defaultVersion = null;

        if ($config['version_strategy']) {
            $defaultVersion = new Reference($config['version_strategy']);
        } else {
            $defaultVersion = $this->createVersion($container, $config['version'], $config['version_format'], $config['json_manifest_path'], '_default');
        }

        $defaultPackage = $this->createPackageDefinition($config['base_path'], $config['base_urls'], $defaultVersion);
        $container->setDefinition('assets._default_package', $defaultPackage);

        $namedPackages = array();
        foreach ($config['packages'] as $name => $package) {
            if (null !== $package['version_strategy']) {
                $version = new Reference($package['version_strategy']);
            } elseif (!array_key_exists('version', $package) && null === $package['json_manifest_path']) {
                // if neither version nor json_manifest_path are specified, use the default
                $version = $defaultVersion;
            } else {
                // let format fallback to main version_format
                $format = $package['version_format'] ?: $config['version_format'];
                $version = isset($package['version']) ? $package['version'] : null;
                $version = $this->createVersion($container, $version, $format, $package['json_manifest_path'], $name);
            }

            $container->setDefinition('assets._package_'.$name, $this->createPackageDefinition($package['base_path'], $package['base_urls'], $version));
            $namedPackages[$name] = new Reference('assets._package_'.$name);
        }

        $container->getDefinition('assets.packages')
            ->replaceArgument(0, new Reference('assets._default_package'))
            ->replaceArgument(1, $namedPackages)
        ;
    }

    /**
     * Returns a definition for an asset package.
     */
    private function createPackageDefinition($basePath, array $baseUrls, Reference $version)
    {
        if ($basePath && $baseUrls) {
            throw new \LogicException('An asset package cannot have base URLs and base paths.');
        }

        $package = new ChildDefinition($baseUrls ? 'assets.url_package' : 'assets.path_package');
        $package
            ->setPublic(false)
            ->replaceArgument(0, $baseUrls ?: $basePath)
            ->replaceArgument(1, $version)
        ;

        return $package;
    }

    private function createVersion(ContainerBuilder $container, $version, $format, $jsonManifestPath, $name)
    {
        // Configuration prevents $version and $jsonManifestPath from being set
        if (null !== $version) {
            $def = new ChildDefinition('assets.static_version_strategy');
            $def
                ->replaceArgument(0, $version)
                ->replaceArgument(1, $format)
            ;
            $container->setDefinition('assets._version_'.$name, $def);

            return new Reference('assets._version_'.$name);
        }

        if (null !== $jsonManifestPath) {
            $def = new ChildDefinition('assets.json_manifest_version_strategy');
            $def->replaceArgument(0, $jsonManifestPath);
            $container->setDefinition('assets._version_'.$name, $def);

            return new Reference('assets._version_'.$name);
        }

        return new Reference('assets.empty_version_strategy');
    }

    private function registerTranslatorConfiguration(array $config, ContainerBuilder $container, LoaderInterface $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            $container->removeDefinition('console.command.translation_debug');
            $container->removeDefinition('console.command.translation_update');

            return;
        }

        $loader->load('translation.xml');

        $container->getDefinition('translator.default')->setPrivate(true);
        $container->getDefinition('translation.loader.php')->setPrivate(true);
        $container->getDefinition('translation.loader.yml')->setPrivate(true);
        $container->getDefinition('translation.loader.xliff')->setPrivate(true);
        $container->getDefinition('translation.loader.po')->setPrivate(true);
        $container->getDefinition('translation.loader.mo')->setPrivate(true);
        $container->getDefinition('translation.loader.qt')->setPrivate(true);
        $container->getDefinition('translation.loader.csv')->setPrivate(true);
        $container->getDefinition('translation.loader.res')->setPrivate(true);
        $container->getDefinition('translation.loader.dat')->setPrivate(true);
        $container->getDefinition('translation.loader.ini')->setPrivate(true);
        $container->getDefinition('translation.loader.json')->setPrivate(true);
        $container->getDefinition('translation.dumper.php')->setPrivate(true);
        $container->getDefinition('translation.dumper.xliff')->setPrivate(true);
        $container->getDefinition('translation.dumper.po')->setPrivate(true);
        $container->getDefinition('translation.dumper.mo')->setPrivate(true);
        $container->getDefinition('translation.dumper.yml')->setPrivate(true);
        $container->getDefinition('translation.dumper.qt')->setPrivate(true);
        $container->getDefinition('translation.dumper.csv')->setPrivate(true);
        $container->getDefinition('translation.dumper.ini')->setPrivate(true);
        $container->getDefinition('translation.dumper.json')->setPrivate(true);
        $container->getDefinition('translation.dumper.res')->setPrivate(true);
        $container->getDefinition('translation.extractor.php')->setPrivate(true);
        $container->getDefinition('translator_listener')->setPrivate(true);
        $container->getDefinition('translation.loader')->setPrivate(true);
        $container->getDefinition('translation.reader')->setPrivate(true);
        $container->getDefinition('translation.extractor')->setPrivate(true);
        $container->getDefinition('translation.writer')->setPrivate(true);

        // Use the "real" translator instead of the identity default
        $container->setAlias('translator', 'translator.default')->setPublic(true);
        $container->setAlias('translator.formatter', new Alias($config['formatter'], false));
        $translator = $container->findDefinition('translator.default');
        $translator->addMethodCall('setFallbackLocales', array($config['fallbacks']));

        $container->setParameter('translator.logging', $config['logging']);
        $container->setParameter('translator.default_path', $config['default_path']);

        // Discover translation directories
        $dirs = array();
        if (class_exists('Symfony\Component\Validator\Validation')) {
            $r = new \ReflectionClass('Symfony\Component\Validator\Validation');

            $dirs[] = \dirname($r->getFileName()).'/Resources/translations';
        }
        if (class_exists('Symfony\Component\Form\Form')) {
            $r = new \ReflectionClass('Symfony\Component\Form\Form');

            $dirs[] = \dirname($r->getFileName()).'/Resources/translations';
        }
        if (class_exists('Symfony\Component\Security\Core\Exception\AuthenticationException')) {
            $r = new \ReflectionClass('Symfony\Component\Security\Core\Exception\AuthenticationException');

            $dirs[] = \dirname(\dirname($r->getFileName())).'/Resources/translations';
        }
        $defaultDir = $container->getParameterBag()->resolveValue($config['default_path']);
        $rootDir = $container->getParameter('kernel.root_dir');
        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            if ($container->fileExists($dir = $bundle['path'].'/Resources/translations')) {
                $dirs[] = $dir;
            }
            if ($container->fileExists($dir = $rootDir.sprintf('/Resources/%s/translations', $name))) {
                $dirs[] = $dir;
            }
        }

        foreach ($config['paths'] as $dir) {
            if ($container->fileExists($dir)) {
                $dirs[] = $dir;
            } else {
                throw new \UnexpectedValueException(sprintf('%s defined in translator.paths does not exist or is not a directory', $dir));
            }
        }

        if ($container->fileExists($defaultDir)) {
            $dirs[] = $defaultDir;
        }
        if ($container->fileExists($dir = $rootDir.'/Resources/translations')) {
            $dirs[] = $dir;
        }

        // Register translation resources
        if ($dirs) {
            $files = array();
            $finder = Finder::create()
                ->followLinks()
                ->files()
                ->filter(function (\SplFileInfo $file) {
                    return 2 === substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename());
                })
                ->in($dirs)
                ->sortByName()
            ;

            foreach ($finder as $file) {
                list(, $locale) = explode('.', $file->getBasename(), 3);
                if (!isset($files[$locale])) {
                    $files[$locale] = array();
                }

                $files[$locale][] = (string) $file;
            }

            $options = array_merge(
                $translator->getArgument(4),
                array('resource_files' => $files)
            );

            $translator->replaceArgument(4, $options);
        }
    }

    private function registerValidationConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->validatorConfigEnabled = $this->isConfigEnabled($container, $config)) {
            return;
        }

        if (!class_exists('Symfony\Component\Validator\Validation')) {
            throw new LogicException('Validation support cannot be enabled as the Validator component is not installed. Try running "composer require symfony/validator".');
        }

        $loader->load('validator.xml');

        $container->getDefinition('validator.builder')->setPrivate(true);
        $container->getDefinition('validator.expression')->setPrivate(true);
        $container->getDefinition('validator.email')->setPrivate(true);

        $validatorBuilder = $container->getDefinition('validator.builder');

        $container->setParameter('validator.translation_domain', $config['translation_domain']);

        $files = array('xml' => array(), 'yml' => array());
        $this->registerValidatorMapping($container, $config, $files);

        if (!empty($files['xml'])) {
            $validatorBuilder->addMethodCall('addXmlMappings', array($files['xml']));
        }

        if (!empty($files['yml'])) {
            $validatorBuilder->addMethodCall('addYamlMappings', array($files['yml']));
        }

        $definition = $container->findDefinition('validator.email');
        $definition->replaceArgument(0, $config['strict_email']);

        if (array_key_exists('enable_annotations', $config) && $config['enable_annotations']) {
            if (!$this->annotationsConfigEnabled) {
                throw new \LogicException('"enable_annotations" on the validator cannot be set as Annotations support is disabled.');
            }

            $validatorBuilder->addMethodCall('enableAnnotationMapping', array(new Reference('annotation_reader')));
        }

        if (array_key_exists('static_method', $config) && $config['static_method']) {
            foreach ($config['static_method'] as $methodName) {
                $validatorBuilder->addMethodCall('addMethodMapping', array($methodName));
            }
        }

        if (isset($config['cache']) && $config['cache']) {
            $container->setParameter(
                'validator.mapping.cache.prefix',
                'validator_'.$this->getKernelRootHash($container)
            );

            $validatorBuilder->addMethodCall('setMetadataCache', array(new Reference($config['cache'])));
        } elseif (!$container->getParameter('kernel.debug')) {
            $validatorBuilder->addMethodCall('setMetadataCache', array(new Reference('validator.mapping.cache.symfony')));
        }
    }

    private function registerValidatorMapping(ContainerBuilder $container, array $config, array &$files)
    {
        $fileRecorder = function ($extension, $path) use (&$files) {
            $files['yaml' === $extension ? 'yml' : $extension][] = $path;
        };

        if (interface_exists('Symfony\Component\Form\FormInterface')) {
            $reflClass = new \ReflectionClass('Symfony\Component\Form\FormInterface');
            $fileRecorder('xml', \dirname($reflClass->getFileName()).'/Resources/config/validation.xml');
        }

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $dirname = $bundle['path'];

            if (
                $container->fileExists($file = $dirname.'/Resources/config/validation.yaml', false) ||
                $container->fileExists($file = $dirname.'/Resources/config/validation.yml', false)
            ) {
                $fileRecorder('yml', $file);
            }

            if ($container->fileExists($file = $dirname.'/Resources/config/validation.xml', false)) {
                $fileRecorder('xml', $file);
            }

            if ($container->fileExists($dir = $dirname.'/Resources/config/validation', '/^$/')) {
                $this->registerMappingFilesFromDir($dir, $fileRecorder);
            }
        }

        $projectDir = $container->getParameter('kernel.project_dir');
        if ($container->fileExists($dir = $projectDir.'/config/validator', '/^$/')) {
            $this->registerMappingFilesFromDir($dir, $fileRecorder);
        }

        $this->registerMappingFilesFromConfig($container, $config, $fileRecorder);
    }

    private function registerMappingFilesFromDir($dir, callable $fileRecorder)
    {
        foreach (Finder::create()->followLinks()->files()->in($dir)->name('/\.(xml|ya?ml)$/')->sortByName() as $file) {
            $fileRecorder($file->getExtension(), $file->getRealPath());
        }
    }

    private function registerMappingFilesFromConfig(ContainerBuilder $container, array $config, callable $fileRecorder)
    {
        foreach ($config['mapping']['paths'] as $path) {
            if (is_dir($path)) {
                $this->registerMappingFilesFromDir($path, $fileRecorder);
                $container->addResource(new DirectoryResource($path, '/^$/'));
            } elseif ($container->fileExists($path, false)) {
                if (!preg_match('/\.(xml|ya?ml)$/', $path, $matches)) {
                    throw new \RuntimeException(sprintf('Unsupported mapping type in "%s", supported types are XML & Yaml.', $path));
                }
                $fileRecorder($matches[1], $path);
            } else {
                throw new \RuntimeException(sprintf('Could not open file or directory "%s".', $path));
            }
        }
    }

    private function registerAnnotationsConfiguration(array $config, ContainerBuilder $container, $loader)
    {
        if (!$this->annotationsConfigEnabled) {
            return;
        }

        if (!class_exists('Doctrine\Common\Annotations\Annotation')) {
            throw new LogicException('Annotations cannot be enabled as the Doctrine Annotation library is not installed.');
        }

        $loader->load('annotations.xml');

        $container->getAlias('annotation_reader')->setPrivate(true);

        if (!method_exists(AnnotationRegistry::class, 'registerUniqueLoader')) {
            $container->getDefinition('annotations.dummy_registry')
                ->setMethodCalls(array(array('registerLoader', array('class_exists'))));
        }

        if ('none' !== $config['cache']) {
            if (!class_exists('Doctrine\Common\Cache\CacheProvider')) {
                throw new LogicException('Annotations cannot be enabled as the Doctrine Cache library is not installed.');
            }

            $cacheService = $config['cache'];

            if ('php_array' === $config['cache']) {
                $cacheService = 'annotations.cache';

                // Enable warmer only if PHP array is used for cache
                $definition = $container->findDefinition('annotations.cache_warmer');
                $definition->addTag('kernel.cache_warmer');

                if (\PHP_VERSION_ID < 70000) {
                    $this->addClassesToCompile(array(
                        'Symfony\Component\Cache\Adapter\PhpArrayAdapter',
                        'Symfony\Component\Cache\DoctrineProvider',
                    ));
                }
            } elseif ('file' === $config['cache']) {
                $cacheDir = $container->getParameterBag()->resolveValue($config['file_cache_dir']);

                if (!is_dir($cacheDir) && false === @mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
                    throw new \RuntimeException(sprintf('Could not create cache directory "%s".', $cacheDir));
                }

                $container
                    ->getDefinition('annotations.filesystem_cache')
                    ->replaceArgument(0, $cacheDir)
                ;

                $cacheService = 'annotations.filesystem_cache';
            }

            $container
                ->getDefinition('annotations.cached_reader')
                ->replaceArgument(2, $config['debug'])
                // temporary property to lazy-reference the cache provider without using it until AddAnnotationsCachedReaderPass runs
                ->setProperty('cacheProviderBackup', new ServiceClosureArgument(new Reference($cacheService)))
                ->addTag('annotations.cached_reader')
            ;

            $container->setAlias('annotation_reader', 'annotations.cached_reader')->setPrivate(true);
            $container->setAlias(Reader::class, new Alias('annotations.cached_reader', false));
        } else {
            $container->removeDefinition('annotations.cached_reader');
        }
    }

    private function registerPropertyAccessConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!class_exists('Symfony\Component\PropertyAccess\PropertyAccessor')) {
            return;
        }

        $loader->load('property_access.xml');

        $container->getDefinition('property_accessor')->setPrivate(true);

        $container
            ->getDefinition('property_accessor')
            ->replaceArgument(0, $config['magic_call'])
            ->replaceArgument(1, $config['throw_exception_on_invalid_index'])
        ;
    }

    private function registerSecurityCsrfConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        if (!class_exists('Symfony\Component\Security\Csrf\CsrfToken')) {
            throw new LogicException('CSRF support cannot be enabled as the Security CSRF component is not installed. Try running "composer require symfony/security-csrf".');
        }

        if (!$this->sessionConfigEnabled) {
            throw new \LogicException('CSRF protection needs sessions to be enabled.');
        }

        // Enable services for CSRF protection (even without forms)
        $loader->load('security_csrf.xml');
    }

    private function registerSerializerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('serializer.xml');

        if (!class_exists(DateIntervalNormalizer::class)) {
            $container->removeDefinition('serializer.normalizer.dateinterval');
        }

        $container->getDefinition('serializer.mapping.cache.symfony')->setPrivate(true);

        $chainLoader = $container->getDefinition('serializer.mapping.chain_loader');

        if (!class_exists('Symfony\Component\PropertyAccess\PropertyAccessor')) {
            $container->removeAlias('serializer.property_accessor');
            $container->removeDefinition('serializer.normalizer.object');
        }

        if (!class_exists(Yaml::class)) {
            $container->removeDefinition('serializer.encoder.yaml');
        }

        $serializerLoaders = array();
        if (isset($config['enable_annotations']) && $config['enable_annotations']) {
            if (!$this->annotationsConfigEnabled) {
                throw new \LogicException('"enable_annotations" on the serializer cannot be set as Annotations support is disabled.');
            }

            $annotationLoader = new Definition(
                'Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader',
                 array(new Reference('annotation_reader'))
            );
            $annotationLoader->setPublic(false);

            $serializerLoaders[] = $annotationLoader;
        }

        $fileRecorder = function ($extension, $path) use (&$serializerLoaders) {
            $definition = new Definition(\in_array($extension, array('yaml', 'yml')) ? 'Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader' : 'Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader', array($path));
            $definition->setPublic(false);
            $serializerLoaders[] = $definition;
        };

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $dirname = $bundle['path'];

            if ($container->fileExists($file = $dirname.'/Resources/config/serialization.xml', false)) {
                $fileRecorder('xml', $file);
            }

            if (
                $container->fileExists($file = $dirname.'/Resources/config/serialization.yaml', false) ||
                $container->fileExists($file = $dirname.'/Resources/config/serialization.yml', false)
            ) {
                $fileRecorder('yml', $file);
            }

            if ($container->fileExists($dir = $dirname.'/Resources/config/serialization', '/^$/')) {
                $this->registerMappingFilesFromDir($dir, $fileRecorder);
            }
        }

        $projectDir = $container->getParameter('kernel.project_dir');
        if ($container->fileExists($dir = $projectDir.'/config/serializer', '/^$/')) {
            $this->registerMappingFilesFromDir($dir, $fileRecorder);
        }

        $this->registerMappingFilesFromConfig($container, $config, $fileRecorder);

        $chainLoader->replaceArgument(0, $serializerLoaders);
        $container->getDefinition('serializer.mapping.cache_warmer')->replaceArgument(0, $serializerLoaders);

        if (isset($config['cache']) && $config['cache']) {
            $container->setParameter(
                'serializer.mapping.cache.prefix',
                'serializer_'.$this->getKernelRootHash($container)
            );

            $container->getDefinition('serializer.mapping.class_metadata_factory')->replaceArgument(
                1, new Reference($config['cache'])
            );
        } elseif (!$container->getParameter('kernel.debug')) {
            $cacheMetadataFactory = new Definition(
                CacheClassMetadataFactory::class,
                array(
                    new Reference('serializer.mapping.cache_class_metadata_factory.inner'),
                    new Reference('serializer.mapping.cache.symfony'),
                )
            );
            $cacheMetadataFactory->setPublic(false);
            $cacheMetadataFactory->setDecoratedService('serializer.mapping.class_metadata_factory');

            $container->setDefinition('serializer.mapping.cache_class_metadata_factory', $cacheMetadataFactory);
        }

        if (isset($config['name_converter']) && $config['name_converter']) {
            $container->getDefinition('serializer.normalizer.object')->replaceArgument(1, new Reference($config['name_converter']));
        }

        if (isset($config['circular_reference_handler']) && $config['circular_reference_handler']) {
            $container->getDefinition('serializer.normalizer.object')->addMethodCall('setCircularReferenceHandler', array(new Reference($config['circular_reference_handler'])));
        }
    }

    private function registerPropertyInfoConfiguration(ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!interface_exists(PropertyInfoExtractorInterface::class)) {
            throw new LogicException('PropertyInfo support cannot be enabled as the PropertyInfo component is not installed. Try running "composer require symfony/property-info".');
        }

        $loader->load('property_info.xml');

        $container->getDefinition('property_info')->setPrivate(true);

        if (interface_exists('phpDocumentor\Reflection\DocBlockFactoryInterface')) {
            $definition = $container->register('property_info.php_doc_extractor', 'Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor');
            $definition->setPrivate(true);
            $definition->addTag('property_info.description_extractor', array('priority' => -1000));
            $definition->addTag('property_info.type_extractor', array('priority' => -1001));
        }
    }

    private function registerLockConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('lock.xml');

        foreach ($config['resources'] as $resourceName => $resourceStores) {
            if (0 === \count($resourceStores)) {
                continue;
            }

            // Generate stores
            $storeDefinitions = array();
            foreach ($resourceStores as $storeDsn) {
                $storeDsn = $container->resolveEnvPlaceholders($storeDsn, null, $usedEnvs);
                switch (true) {
                    case 'flock' === $storeDsn:
                        $storeDefinition = new Reference('lock.store.flock');
                        break;
                    case 'semaphore' === $storeDsn:
                        $storeDefinition = new Reference('lock.store.semaphore');
                        break;
                    case $usedEnvs || preg_match('#^[a-z]++://#', $storeDsn):
                        if (!$container->hasDefinition($connectionDefinitionId = $container->hash($storeDsn))) {
                            $connectionDefinition = new Definition(\stdClass::class);
                            $connectionDefinition->setPublic(false);
                            $connectionDefinition->setFactory(array(AbstractAdapter::class, 'createConnection'));
                            $connectionDefinition->setArguments(array($storeDsn, array('lazy' => true)));
                            $container->setDefinition($connectionDefinitionId, $connectionDefinition);
                        }

                        $storeDefinition = new Definition(StoreInterface::class);
                        $storeDefinition->setPublic(false);
                        $storeDefinition->setFactory(array(StoreFactory::class, 'createStore'));
                        $storeDefinition->setArguments(array(new Reference($connectionDefinitionId)));

                        $container->setDefinition($storeDefinitionId = 'lock.'.$resourceName.'.store.'.$container->hash($storeDsn), $storeDefinition);

                        $storeDefinition = new Reference($storeDefinitionId);
                        break;
                    default:
                        throw new InvalidArgumentException(sprintf('Lock store DSN "%s" is not valid in resource "%s"', $storeDsn, $resourceName));
                }

                $storeDefinitions[] = $storeDefinition;
            }

            // Wrap array of stores with CombinedStore
            if (\count($storeDefinitions) > 1) {
                $combinedDefinition = new ChildDefinition('lock.store.combined.abstract');
                $combinedDefinition->replaceArgument(0, $storeDefinitions);
                $container->setDefinition('lock.'.$resourceName.'.store', $combinedDefinition);
            } else {
                $container->setAlias('lock.'.$resourceName.'.store', new Alias((string) $storeDefinitions[0], false));
            }

            // Generate factories for each resource
            $factoryDefinition = new ChildDefinition('lock.factory.abstract');
            $factoryDefinition->replaceArgument(0, new Reference('lock.'.$resourceName.'.store'));
            $container->setDefinition('lock.'.$resourceName.'.factory', $factoryDefinition);

            // Generate services for lock instances
            $lockDefinition = new Definition(Lock::class);
            $lockDefinition->setPublic(false);
            $lockDefinition->setFactory(array(new Reference('lock.'.$resourceName.'.factory'), 'createLock'));
            $lockDefinition->setArguments(array($resourceName));
            $container->setDefinition('lock.'.$resourceName, $lockDefinition);

            // provide alias for default resource
            if ('default' === $resourceName) {
                $container->setAlias('lock.store', new Alias('lock.'.$resourceName.'.store', false));
                $container->setAlias('lock.factory', new Alias('lock.'.$resourceName.'.factory', false));
                $container->setAlias('lock', new Alias('lock.'.$resourceName, false));
                $container->setAlias(StoreInterface::class, new Alias('lock.store', false));
                $container->setAlias(Factory::class, new Alias('lock.factory', false));
                $container->setAlias(LockInterface::class, new Alias('lock', false));
            }
        }
    }

    private function registerCacheConfiguration(array $config, ContainerBuilder $container)
    {
        $version = new Parameter('container.build_id');
        $container->getDefinition('cache.adapter.apcu')->replaceArgument(2, $version);
        $container->getDefinition('cache.adapter.system')->replaceArgument(2, $version);
        $container->getDefinition('cache.adapter.filesystem')->replaceArgument(2, $config['directory']);

        if (isset($config['prefix_seed'])) {
            $container->setParameter('cache.prefix.seed', $config['prefix_seed']);
        }
        if ($container->hasParameter('cache.prefix.seed')) {
            // Inline any env vars referenced in the parameter
            $container->setParameter('cache.prefix.seed', $container->resolveEnvPlaceholders($container->getParameter('cache.prefix.seed'), true));
        }
        foreach (array('doctrine', 'psr6', 'redis', 'memcached') as $name) {
            if (isset($config[$name = 'default_'.$name.'_provider'])) {
                $container->setAlias('cache.'.$name, new Alias(Compiler\CachePoolPass::getServiceProvider($container, $config[$name]), false));
            }
        }
        foreach (array('app', 'system') as $name) {
            $config['pools']['cache.'.$name] = array(
                'adapter' => $config[$name],
                'public' => true,
            );
        }
        foreach ($config['pools'] as $name => $pool) {
            $definition = new ChildDefinition($pool['adapter']);
            $definition->setPublic($pool['public']);
            unset($pool['adapter'], $pool['public']);

            $definition->addTag('cache.pool', $pool);
            $container->setDefinition($name, $definition);
        }

        if (method_exists(PropertyAccessor::class, 'createCache')) {
            $propertyAccessDefinition = $container->register('cache.property_access', AdapterInterface::class);
            $propertyAccessDefinition->setPublic(false);

            if (!$container->getParameter('kernel.debug')) {
                $propertyAccessDefinition->setFactory(array(PropertyAccessor::class, 'createCache'));
                $propertyAccessDefinition->setArguments(array(null, null, $version, new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)));
                $propertyAccessDefinition->addTag('cache.pool', array('clearer' => 'cache.system_clearer'));
                $propertyAccessDefinition->addTag('monolog.logger', array('channel' => 'cache'));
            } else {
                $propertyAccessDefinition->setClass(ArrayAdapter::class);
                $propertyAccessDefinition->setArguments(array(0, false));
            }
        }

        if (\PHP_VERSION_ID < 70000) {
            $this->addClassesToCompile(array(
                'Symfony\Component\Cache\Adapter\ApcuAdapter',
                'Symfony\Component\Cache\Adapter\FilesystemAdapter',
                'Symfony\Component\Cache\CacheItem',
            ));
        }
    }

    /**
     * Gets a hash of the kernel root directory.
     *
     * @return string
     */
    private function getKernelRootHash(ContainerBuilder $container)
    {
        if (!$this->kernelRootHash) {
            $this->kernelRootHash = hash('sha256', $container->getParameter('kernel.root_dir'));
        }

        return $this->kernelRootHash;
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return \dirname(__DIR__).'/Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/symfony';
    }
}
