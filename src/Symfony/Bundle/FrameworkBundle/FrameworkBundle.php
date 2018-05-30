<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddAnnotationsCachedReaderPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddDebugLogProcessorPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CacheCollectorPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolClearerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolPrunerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DataCollectorTranslatorPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TemplatingPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ProfilerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\LoggingTranslatorPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddExpressionLanguageProvidersPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ContainerBuilderDebugDumpPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\UnusedTagsPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\WorkflowGuardListenerPass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass;
use Symfony\Component\HttpKernel\DependencyInjection\LoggerPass;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Symfony\Component\HttpKernel\DependencyInjection\RemoveEmptyControllerArgumentLocatorsPass;
use Symfony\Component\HttpKernel\DependencyInjection\ResettableServicePass;
use Symfony\Component\PropertyInfo\DependencyInjection\PropertyInfoPass;
use Symfony\Component\Routing\DependencyInjection\RoutingResolverPass;
use Symfony\Component\Serializer\DependencyInjection\SerializerPass;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\HttpKernel\DependencyInjection\FragmentRendererPass;
use Symfony\Component\Form\DependencyInjection\FormPass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\Translation\DependencyInjection\TranslationDumperPass;
use Symfony\Component\Translation\DependencyInjection\TranslationExtractorPass;
use Symfony\Component\Translation\DependencyInjection\TranslatorPass;
use Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass;
use Symfony\Component\Validator\DependencyInjection\AddValidatorInitializersPass;
use Symfony\Component\Workflow\DependencyInjection\ValidateWorkflowsPass;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FrameworkBundle extends Bundle
{
    public function boot()
    {
        ErrorHandler::register(null, false)->throwAt($this->container->getParameter('debug.error_handler.throw_at'), true);

        if ($this->container->hasParameter('kernel.trusted_proxies')) {
            @trigger_error('The "kernel.trusted_proxies" parameter is deprecated since Symfony 3.3 and will be removed in 4.0. Use the Request::setTrustedProxies() method in your front controller instead.', E_USER_DEPRECATED);

            if ($trustedProxies = $this->container->getParameter('kernel.trusted_proxies')) {
                Request::setTrustedProxies($trustedProxies, Request::getTrustedHeaderSet());
            }
        }

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
        $this->addCompilerPassIfExists($container, AddConsoleCommandPass::class, PassConfig::TYPE_BEFORE_REMOVING);
        if (class_exists(TranslatorPass::class)) {
            // Arguments to be removed in 4.0, relying on the default values
            $container->addCompilerPass(new TranslatorPass('translator.default', 'translation.loader'));
        }
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
