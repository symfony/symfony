<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle;

use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddAnnotationsCachedReaderPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddDebugLogProcessorPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CacheCollectorPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolClearerPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolPrunerPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DataCollectorTranslatorPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TemplatingPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ProfilerPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\LoggingTranslatorPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddExpressionLanguageProvidersPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ContainerBuilderDebugDumpPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TestServiceContainerWeakRefPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TestServiceContainerRealRefPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\UnusedTagsPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\WorkflowGuardListenerPass;
use Symphony\Component\Console\Application;
use Symphony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symphony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass;
use Symphony\Component\HttpKernel\DependencyInjection\LoggerPass;
use Symphony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Symphony\Component\HttpKernel\DependencyInjection\RemoveEmptyControllerArgumentLocatorsPass;
use Symphony\Component\HttpKernel\DependencyInjection\ResettableServicePass;
use Symphony\Component\Messenger\DependencyInjection\MessengerPass;
use Symphony\Component\PropertyInfo\DependencyInjection\PropertyInfoPass;
use Symphony\Component\Routing\DependencyInjection\RoutingResolverPass;
use Symphony\Component\Serializer\DependencyInjection\SerializerPass;
use Symphony\Component\Debug\ErrorHandler;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Compiler\PassConfig;
use Symphony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symphony\Component\HttpKernel\DependencyInjection\FragmentRendererPass;
use Symphony\Component\Form\DependencyInjection\FormPass;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\Bundle\Bundle;
use Symphony\Component\HttpKernel\KernelEvents;
use Symphony\Component\Config\Resource\ClassExistenceResource;
use Symphony\Component\Translation\DependencyInjection\TranslationDumperPass;
use Symphony\Component\Translation\DependencyInjection\TranslationExtractorPass;
use Symphony\Component\Translation\DependencyInjection\TranslatorPass;
use Symphony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass;
use Symphony\Component\Validator\DependencyInjection\AddValidatorInitializersPass;
use Symphony\Component\Workflow\DependencyInjection\ValidateWorkflowsPass;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class FrameworkBundle extends Bundle
{
    public function boot()
    {
        ErrorHandler::register(null, false)->throwAt($this->container->getParameter('debug.error_handler.throw_at'), true);

        if ($this->container->getParameter('kernel.http_method_override')) {
            Request::enableHttpMethodParameterOverride();
        }

        if ($trustedHosts = $this->container->getParameter('kernel.trusted_hosts')) {
            Request::setTrustedHosts($trustedHosts);
        }
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $hotPathEvents = array(
            KernelEvents::REQUEST,
            KernelEvents::CONTROLLER,
            KernelEvents::CONTROLLER_ARGUMENTS,
            KernelEvents::RESPONSE,
            KernelEvents::FINISH_REQUEST,
        );

        $container->addCompilerPass(new LoggerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -32);
        $container->addCompilerPass(new RegisterControllerArgumentLocatorsPass());
        $container->addCompilerPass(new RemoveEmptyControllerArgumentLocatorsPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new RoutingResolverPass());
        $container->addCompilerPass(new ProfilerPass());
        // must be registered before removing private services as some might be listeners/subscribers
        // but as late as possible to get resolved parameters
        $container->addCompilerPass((new RegisterListenersPass())->setHotPathEvents($hotPathEvents), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new TemplatingPass());
        $this->addCompilerPassIfExists($container, AddConstraintValidatorsPass::class, PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new AddAnnotationsCachedReaderPass(), PassConfig::TYPE_AFTER_REMOVING, -255);
        $this->addCompilerPassIfExists($container, AddValidatorInitializersPass::class);
        $this->addCompilerPassIfExists($container, AddConsoleCommandPass::class);
        $this->addCompilerPassIfExists($container, TranslatorPass::class);
        $container->addCompilerPass(new LoggingTranslatorPass());
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());
        $this->addCompilerPassIfExists($container, TranslationExtractorPass::class);
        $this->addCompilerPassIfExists($container, TranslationDumperPass::class);
        $container->addCompilerPass(new FragmentRendererPass());
        $this->addCompilerPassIfExists($container, SerializerPass::class);
        $this->addCompilerPassIfExists($container, PropertyInfoPass::class);
        $container->addCompilerPass(new DataCollectorTranslatorPass());
        $container->addCompilerPass(new ControllerArgumentValueResolverPass());
        $container->addCompilerPass(new CachePoolPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 32);
        $this->addCompilerPassIfExists($container, ValidateWorkflowsPass::class);
        $container->addCompilerPass(new CachePoolClearerPass(), PassConfig::TYPE_AFTER_REMOVING);
        $container->addCompilerPass(new CachePoolPrunerPass(), PassConfig::TYPE_AFTER_REMOVING);
        $this->addCompilerPassIfExists($container, FormPass::class);
        $container->addCompilerPass(new WorkflowGuardListenerPass());
        $container->addCompilerPass(new ResettableServicePass());
        $container->addCompilerPass(new TestServiceContainerWeakRefPass(), PassConfig::TYPE_BEFORE_REMOVING, -32);
        $container->addCompilerPass(new TestServiceContainerRealRefPass(), PassConfig::TYPE_AFTER_REMOVING);
        $this->addCompilerPassIfExists($container, MessengerPass::class);

        if ($container->getParameter('kernel.debug')) {
            $container->addCompilerPass(new AddDebugLogProcessorPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -32);
            $container->addCompilerPass(new UnusedTagsPass(), PassConfig::TYPE_AFTER_REMOVING);
            $container->addCompilerPass(new ContainerBuilderDebugDumpPass(), PassConfig::TYPE_BEFORE_REMOVING, -255);
            $container->addCompilerPass(new CacheCollectorPass(), PassConfig::TYPE_BEFORE_REMOVING);
        }
    }

    private function addCompilerPassIfExists(ContainerBuilder $container, $class, $type = PassConfig::TYPE_BEFORE_OPTIMIZATION, $priority = 0)
    {
        $container->addResource(new ClassExistenceResource($class));

        if (class_exists($class)) {
            $container->addCompilerPass(new $class(), $type, $priority);
        }
    }

    public function registerCommands(Application $application)
    {
        // noop
    }
}
