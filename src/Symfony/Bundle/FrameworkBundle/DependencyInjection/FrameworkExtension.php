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

use Doctrine\Common\Annotations\Reader;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\ResourceCheckerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\Workflow;

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

    /**
     * @var string|null
     */
    private $kernelRootHash;

    /**
     * Responds to the app.config configuration parameter.
     *
     * @param array            $configs
     * @param ContainerBuilder $container
     *
     * @throws LogicException
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(dirname(__DIR__).'/Resources/config'));

        $loader->load('web.xml');
        $loader->load('services.xml');

        if (\PHP_VERSION_ID < 70000) {
            $definition = $container->getDefinition('kernel.class_cache.cache_warmer');
            $definition->addTag('kernel.cache_warmer');
            // Ignore deprecation for PHP versions below 7.0
            $definition->setDeprecated(false);
        }

        $loader->load('fragment_renderer.xml');

        if (class_exists(Application::class)) {
            $loader->load('console.xml');
        }

        // Property access is used by both the Form and the Validator component
        $loader->load('property_access.xml');

        // Load Cache configuration first as it is used by other components
        $loader->load('cache.xml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $this->annotationsConfigEnabled = $this->isConfigEnabled($container, $config['annotations']);
        $this->translationConfigEnabled = $this->isConfigEnabled($container, $config['translator']);

        // A translator must always be registered (as support is included by
        // default in the Form and Validator component). If disabled, an identity
        // translator will be used and everything will still work as expected.
        if ($this->isConfigEnabled($container, $config['translator']) || $this->isConfigEnabled($container, $config['form']) || $this->isConfigEnabled($container, $config['validation'])) {
            if (!class_exists('Symfony\Component\Translation\Translator') && $this->isConfigEnabled($container, $config['translator'])) {
                throw new LogicException('Translation support cannot be enabled as the Translation component is not installed.');
            }

            if (!class_exists('Symfony\Component\Translation\Translator') && $this->isConfigEnabled($container, $config['form'])) {
                throw new LogicException('Form support cannot be enabled as the Translation component is not installed.');
            }

            if (!class_exists('Symfony\Component\Translation\Translator') && $this->isConfigEnabled($container, $config['validation'])) {
                throw new LogicException('Validation support cannot be enabled as the Translation component is not installed.');
            }

            $loader->load('identity_translator.xml');
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
        }

        if ($this->isConfigEnabled($container, $config['session'])) {
            $this->sessionConfigEnabled = true;
            $this->registerSessionConfiguration($config['session'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['request'])) {
            $this->registerRequestConfiguration($config['request'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['form'])) {
            $this->formConfigEnabled = true;
            $this->registerFormConfiguration($config, $container, $loader);
            $config['validation']['enabled'] = true;

            if (!class_exists('Symfony\Component\Validator\Validation')) {
                throw new LogicException('The Validator component is required to use the Form component.');
            }
        }

        $this->registerSecurityCsrfConfiguration($config['csrf_protection'], $container, $loader);

        if ($this->isConfigEnabled($container, $config['assets'])) {
            if (!class_exists('Symfony\Component\Asset\Package')) {
                throw new LogicException('Asset support cannot be enabled as the Asset component is not installed.');
            }

            $this->registerAssetsConfiguration($config['assets'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['templating'])) {
            if (!class_exists('Symfony\Component\Templating\PhpEngine')) {
                throw new LogicException('Templating support cannot be enabled as the Templating component is not installed.');
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

        if ($this->isConfigEnabled($container, $config['router'])) {
            $this->registerRouterConfiguration($config['router'], $container, $loader);
        }

        $this->registerAnnotationsConfiguration($config['annotations'], $container, $loader);
        $this->registerPropertyAccessConfiguration($config['property_access'], $container);

        if ($this->isConfigEnabled($container, $config['serializer'])) {
            $this->registerSerializerConfiguration($config['serializer'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['property_info'])) {
            $this->registerPropertyInfoConfiguration($config['property_info'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['web_link'])) {
            if (!class_exists(HttpHeaderSerializer::class)) {
                throw new LogicException('WebLink support cannot be enabled as the WebLink component is not installed.');
            }

            $loader->load('web_link.xml');
        }

        $this->addAnnotatedClassesToCompile(array(
            '**\\Controller\\',
            '**\\Entity\\',

            // Added explicitly so that we don't rely on the class map being dumped to make it work
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\Controller',
        ));

        $container->registerForAutoconfiguration(Command::class)
            ->addTag('console.command');
        $container->registerForAutoconfiguration(ResourceCheckerInterface::class)
            ->addTag('config_cache.resource_checker');
        $container->registerForAutoconfiguration(ServiceSubscriberInterface::class)
            ->addTag('container.service_subscriber');
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

    /**
     * Loads Form configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     *
     * @throws \LogicException
     */
    private function registerFormConfiguration($config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('form.xml');
        if (null === $config['form']['csrf_protection']['enabled']) {
            $config['form']['csrf_protection']['enabled'] = $config['csrf_protection']['enabled'];
        }

        if ($this->isConfigEnabled($container, $config['form']['csrf_protection'])) {
            $loader->load('form_csrf.xml');

            $container->setParameter('form.type_extension.csrf.enabled', true);
            $container->setParameter('form.type_extension.csrf.field_name', $config['form']['csrf_protection']['field_name']);
        } else {
            $container->setParameter('form.type_extension.csrf.enabled', false);
        }
    }

    /**
     * Loads the ESI configuration.
     *
     * @param array            $config    An ESI configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerEsiConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('esi.xml');
    }

    /**
     * Loads the SSI configuration.
     *
     * @param array            $config    An SSI configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerSsiConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('ssi.xml');
    }

    /**
     * Loads the fragments configuration.
     *
     * @param array            $config    A fragments configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerFragmentsConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('fragment_listener.xml');
        $container->setParameter('fragment.path', $config['path']);
    }

    /**
     * Loads the profiler configuration.
     *
     * @param array            $config    A profiler configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     *
     * @throws \LogicException
     */
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

        if ($this->formConfigEnabled) {
            $loader->load('form_debug.xml');
        }

        if ($this->translationConfigEnabled) {
            $loader->load('translation_debug.xml');
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
                $container->setAlias('profiler.request_matcher', $config['matcher']['service']);
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

        if (!$config['collect']) {
            $container->getDefinition('profiler')->addMethodCall('disable', array());
        }
    }

    /**
     * Loads the workflow configuration.
     *
     * @param array            $workflows A workflow configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerWorkflowConfiguration(array $workflows, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$workflows) {
            return;
        }

        if (!class_exists(Workflow\Workflow::class)) {
            throw new LogicException('Workflow support cannot be enabled as the Workflow component is not installed.');
        }

        $loader->load('workflow.xml');

        $registryDefinition = $container->getDefinition('workflow.registry');

        foreach ($workflows as $name => $workflow) {
            if (!array_key_exists('type', $workflow)) {
                $workflow['type'] = 'workflow';
                @trigger_error(sprintf('The "type" option of the "framework.workflows.%s" configuration entry must be defined since Symfony 3.3. The default value will be "state_machine" in Symfony 4.0.', $name), E_USER_DEPRECATED);
            }
            $type = $workflow['type'];

            $transitions = array();
            foreach ($workflow['transitions'] as $transition) {
                if ('workflow' === $type) {
                    $transitions[] = new Definition(Workflow\Transition::class, array($transition['name'], $transition['from'], $transition['to']));
                } elseif ('state_machine' === $type) {
                    foreach ($transition['from'] as $from) {
                        foreach ($transition['to'] as $to) {
                            $transitions[] = new Definition(Workflow\Transition::class, array($transition['name'], $from, $to));
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
            $workflowDefinition->replaceArgument(0, $definitionDefinition);
            if (isset($markingStoreDefinition)) {
                $workflowDefinition->replaceArgument(1, $markingStoreDefinition);
            }
            $workflowDefinition->replaceArgument(3, $name);

            // Store to container
            $workflowId = sprintf('%s.%s', $type, $name);
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
                $listener->addTag('monolog.logger', array('channel' => 'workflow'));
                $listener->addTag('kernel.event_listener', array('event' => sprintf('workflow.%s.leave', $name), 'method' => 'onLeave'));
                $listener->addTag('kernel.event_listener', array('event' => sprintf('workflow.%s.transition', $name), 'method' => 'onTransition'));
                $listener->addTag('kernel.event_listener', array('event' => sprintf('workflow.%s.enter', $name), 'method' => 'onEnter'));
                $listener->addArgument(new Reference('logger'));
                $container->setDefinition(sprintf('%s.listener.audit_trail', $workflowId), $listener);
            }

            // Add Guard Listener
            $guard = new Definition(Workflow\EventListener\GuardListener::class);
            $configuration = array();
            foreach ($workflow['transitions'] as $transitionName => $config) {
                if (!isset($config['guard'])) {
                    continue;
                }

                if (!class_exists(ExpressionLanguage::class)) {
                    throw new LogicException('Cannot guard workflows as the ExpressionLanguage component is not installed.');
                }

                if (!class_exists(Security::class)) {
                    throw new LogicException('Cannot guard workflows as the Security component is not installed.');
                }

                $eventName = sprintf('workflow.%s.guard.%s', $name, $transitionName);
                $guard->addTag('kernel.event_listener', array('event' => $eventName, 'method' => 'onTransition'));
                $configuration[$eventName] = $config['guard'];
            }
            if ($configuration) {
                $guard->setArguments(array(
                    $configuration,
                    new Reference('workflow.security.expression_language'),
                    new Reference('security.token_storage'),
                    new Reference('security.authorization_checker'),
                    new Reference('security.authentication.trust_resolver'),
                    new Reference('security.role_hierarchy'),
                ));

                $container->setDefinition(sprintf('%s.listener.guard', $workflowId), $guard);
                $container->setParameter('workflow.has_guard_listeners', true);
            }
        }
    }

    /**
     * Loads the debug configuration.
     *
     * @param array            $config    A php errors configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerDebugConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('debug_prod.xml');

        $debug = $container->getParameter('kernel.debug');

        if ($debug) {
            $loader->load('debug.xml');
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

    /**
     * Loads the router configuration.
     *
     * @param array            $config    A router configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerRouterConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('routing.xml');

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
    }

    /**
     * Loads the session configuration.
     *
     * @param array            $config    A session configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerSessionConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('session.xml');

        // session storage
        $container->setAlias('session.storage', $config['storage_id']);
        $options = array();
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
            $handlerId = $config['handler_id'];

            if ($config['metadata_update_threshold'] > 0) {
                $container->getDefinition('session.handler.write_check')->addArgument(new Reference($handlerId));
                $handlerId = 'session.handler.write_check';
            }

            $container->setAlias('session.handler', $handlerId);
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

    /**
     * Loads the request configuration.
     *
     * @param array            $config    A request configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerRequestConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if ($config['formats']) {
            $loader->load('request.xml');
            $container
                ->getDefinition('request.add_request_formats_listener')
                ->replaceArgument(0, $config['formats'])
            ;
        }
    }

    /**
     * Loads the templating configuration.
     *
     * @param array            $config    A templating configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerTemplatingConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('templating.xml');

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
            if (1 === count($loaders)) {
                $container->setAlias('templating.loader', (string) reset($loaders));
            } else {
                $container->getDefinition('templating.loader.chain')->addArgument($loaders);
                $container->setAlias('templating.loader', 'templating.loader.chain');
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
        if (1 === count($engines)) {
            $container->setAlias('templating', (string) reset($engines));
        } else {
            foreach ($engines as $engine) {
                $container->getDefinition('templating.engine.delegating')->addMethodCall('addEngine', array($engine));
            }
            $container->setAlias('templating', 'templating.engine.delegating');
        }

        $container->getDefinition('fragment.renderer.hinclude')
            ->addTag('kernel.fragment_renderer', array('alias' => 'hinclude'))
            ->replaceArgument(0, new Reference('templating'))
        ;

        // configure the PHP engine if needed
        if (in_array('php', $config['engines'], true)) {
            $loader->load('templating_php.xml');

            $container->setParameter('templating.helper.form.resources', $config['form']['resources']);

            if ($container->getParameter('kernel.debug')) {
                $loader->load('templating_debug.xml');

                $container->setDefinition('templating.engine.php', $container->findDefinition('debug.templating.engine.php'));
                $container->setAlias('debug.templating.engine.php', 'templating.engine.php');
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

    /**
     * Loads the assets configuration.
     *
     * @param array            $config    A assets configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerAssetsConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('assets.xml');

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

    /**
     * Loads the translator configuration.
     *
     * @param array            $config    A translator configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    private function registerTranslatorConfiguration(array $config, ContainerBuilder $container, LoaderInterface $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('translation.xml');

        // Use the "real" translator instead of the identity default
        $container->setAlias('translator', 'translator.default');
        $translator = $container->findDefinition('translator.default');
        $translator->addMethodCall('setFallbackLocales', array($config['fallbacks']));

        $container->setParameter('translator.logging', $config['logging']);

        // Discover translation directories
        $dirs = array();
        if (class_exists('Symfony\Component\Validator\Validation')) {
            $r = new \ReflectionClass('Symfony\Component\Validator\Validation');

            $dirs[] = dirname($r->getFileName()).'/Resources/translations';
        }
        if (class_exists('Symfony\Component\Form\Form')) {
            $r = new \ReflectionClass('Symfony\Component\Form\Form');

            $dirs[] = dirname($r->getFileName()).'/Resources/translations';
        }
        if (class_exists('Symfony\Component\Security\Core\Exception\AuthenticationException')) {
            $r = new \ReflectionClass('Symfony\Component\Security\Core\Exception\AuthenticationException');

            $dirs[] = dirname(dirname($r->getFileName())).'/Resources/translations';
        }
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

    /**
     * Loads the validator configuration.
     *
     * @param array            $config    A validation configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerValidationConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        if (!class_exists('Symfony\Component\Validator\Validation')) {
            throw new LogicException('Validation support cannot be enabled as the Validator component is not installed.');
        }

        $loader->load('validator.xml');

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
            @trigger_error('The "framework.validation.cache" option is deprecated since Symfony 3.2 and will be removed in 4.0. Configure the "cache.validator" service under "framework.cache.pools" instead.', E_USER_DEPRECATED);

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
            $fileRecorder('xml', dirname($reflClass->getFileName()).'/Resources/config/validation.xml');
        }

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $dirname = $bundle['path'];

            if ($container->fileExists($file = $dirname.'/Resources/config/validation.yml', false)) {
                $fileRecorder('yml', $file);
            }

            if ($container->fileExists($file = $dirname.'/Resources/config/validation.xml', false)) {
                $fileRecorder('xml', $file);
            }

            if ($container->fileExists($dir = $dirname.'/Resources/config/validation', '/^$/')) {
                $this->registerMappingFilesFromDir($dir, $fileRecorder);
            }
        }

        $this->registerMappingFilesFromConfig($container, $config, $fileRecorder);
    }

    private function registerMappingFilesFromDir($dir, callable $fileRecorder)
    {
        foreach (Finder::create()->followLinks()->files()->in($dir)->name('/\.(xml|ya?ml)$/') as $file) {
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

        if ('none' !== $config['cache']) {
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
                ->addTag('annotations.cached_reader', array('provider' => $cacheService))
            ;
            $container->setAlias('annotation_reader', 'annotations.cached_reader');
            $container->setAlias(Reader::class, new Alias('annotations.cached_reader', false));
        } else {
            $container->removeDefinition('annotations.cached_reader');
        }
    }

    private function registerPropertyAccessConfiguration(array $config, ContainerBuilder $container)
    {
        $container
            ->getDefinition('property_accessor')
            ->replaceArgument(0, $config['magic_call'])
            ->replaceArgument(1, $config['throw_exception_on_invalid_index'])
        ;
    }

    /**
     * Loads the security configuration.
     *
     * @param array            $config    A CSRF configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     *
     * @throws \LogicException
     */
    private function registerSecurityCsrfConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        if (!class_exists('Symfony\Component\Security\Csrf\CsrfToken')) {
            throw new LogicException('CSRF support cannot be enabled as the Security CSRF component is not installed.');
        }

        if (!$this->sessionConfigEnabled) {
            throw new \LogicException('CSRF protection needs sessions to be enabled.');
        }

        // Enable services for CSRF protection (even without forms)
        $loader->load('security_csrf.xml');
    }

    /**
     * Loads the serializer configuration.
     *
     * @param array            $config    A serializer configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerSerializerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (class_exists('Symfony\Component\Serializer\Normalizer\DataUriNormalizer')) {
            // Run before serializer.normalizer.object
            $definition = $container->register('serializer.normalizer.data_uri', DataUriNormalizer::class);
            $definition->setPublic(false);
            $definition->addTag('serializer.normalizer', array('priority' => -920));
        }

        if (class_exists('Symfony\Component\Serializer\Normalizer\DateTimeNormalizer')) {
            // Run before serializer.normalizer.object
            $definition = $container->register('serializer.normalizer.datetime', DateTimeNormalizer::class);
            $definition->setPublic(false);
            $definition->addTag('serializer.normalizer', array('priority' => -910));
        }

        if (class_exists('Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer')) {
            // Run before serializer.normalizer.object
            $definition = $container->register('serializer.normalizer.json_serializable', JsonSerializableNormalizer::class);
            $definition->setPublic(false);
            $definition->addTag('serializer.normalizer', array('priority' => -900));
        }

        if (class_exists(YamlEncoder::class) && defined('Symfony\Component\Yaml\Yaml::DUMP_OBJECT')) {
            $definition = $container->register('serializer.encoder.yaml', YamlEncoder::class);
            $definition->setPublic(false);
            $definition->addTag('serializer.encoder');
        }

        if (class_exists(CsvEncoder::class)) {
            $definition = $container->register('serializer.encoder.csv', CsvEncoder::class);
            $definition->setPublic(false);
            $definition->addTag('serializer.encoder');
        }

        $loader->load('serializer.xml');
        $chainLoader = $container->getDefinition('serializer.mapping.chain_loader');

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
            $definition = new Definition(in_array($extension, array('yaml', 'yml')) ? 'Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader' : 'Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader', array($path));
            $definition->setPublic(false);
            $serializerLoaders[] = $definition;
        };

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $dirname = $bundle['path'];

            if ($container->fileExists($file = $dirname.'/Resources/config/serialization.xml', false)) {
                $fileRecorder('xml', $file);
            }

            if ($container->fileExists($file = $dirname.'/Resources/config/serialization.yml', false)) {
                $fileRecorder('yml', $file);
            }

            if ($container->fileExists($dir = $dirname.'/Resources/config/serialization', '/^$/')) {
                $this->registerMappingFilesFromDir($dir, $fileRecorder);
            }
        }

        $this->registerMappingFilesFromConfig($container, $config, $fileRecorder);

        $chainLoader->replaceArgument(0, $serializerLoaders);
        $container->getDefinition('serializer.mapping.cache_warmer')->replaceArgument(0, $serializerLoaders);

        if (isset($config['cache']) && $config['cache']) {
            @trigger_error('The "framework.serializer.cache" option is deprecated since Symfony 3.1 and will be removed in 4.0. Configure the "cache.serializer" service under "framework.cache.pools" instead.', E_USER_DEPRECATED);

            $container->setParameter(
                'serializer.mapping.cache.prefix',
                'serializer_'.$this->getKernelRootHash($container)
            );

            $container->getDefinition('serializer.mapping.class_metadata_factory')->replaceArgument(
                1, new Reference($config['cache'])
            );
        } elseif (!$container->getParameter('kernel.debug') && class_exists(CacheClassMetadataFactory::class)) {
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

    /**
     * Loads property info configuration.
     *
     * @param array            $config
     * @param ContainerBuilder $container
     * @param XmlFileLoader    $loader
     */
    private function registerPropertyInfoConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('property_info.xml');

        if (interface_exists('phpDocumentor\Reflection\DocBlockFactoryInterface')) {
            $definition = $container->register('property_info.php_doc_extractor', 'Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor');
            $definition->addTag('property_info.description_extractor', array('priority' => -1000));
            $definition->addTag('property_info.type_extractor', array('priority' => -1001));
        }
    }

    private function registerCacheConfiguration(array $config, ContainerBuilder $container)
    {
        $version = substr(str_replace('/', '-', base64_encode(hash('sha256', uniqid(mt_rand(), true), true))), 0, 22);
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
                $propertyAccessDefinition->addTag('cache.pool', array('clearer' => 'cache.default_clearer'));
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
     * @param ContainerBuilder $container
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
        return dirname(__DIR__).'/Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/symfony';
    }
}
