<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddConstraintValidatorsPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddFieldFactoryGuessersPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TemplatingPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RegisterKernelListenersPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddSecurityVotersPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ConverterManagerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RoutingResolverPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ProfilerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddClassesToCachePass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslatorPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddCacheWarmerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FrameworkBundle extends Bundle
{
    /**
     * Boots the Bundle.
     */
    public function boot()
    {
        $container = $this->container;

        if ($container->has('error_handler')) {
            $container->get('error_handler');
        }

        if ($this->container->hasParameter('document_root')) {
            File::setDocumentRoot($this->container->getParameter('document_root'));
        }

        // the session ID should always be included in the CSRF token, even
        // if default CSRF protection is not enabled
        if ($container->has('form.default_context') && $container->has('session')) {
            $addSessionId = function () use ($container) {
                // automatically starts the session when the CSRF token is
                // generated
                $container->get('session')->start();

                return $container->get('session')->getId();
            };

//            $container->getDefinition('form.default_context')
//                    ->addMethodCall('addCsrfSecret', array($addSessionId));
//
//                    var_dump($container->getDefinition('form.default_context'));
        }
    }

    public function registerExtensions(ContainerBuilder $container)
    {
        parent::registerExtensions($container);

        $container->addScope('request');

        $container->addCompilerPass(new AddSecurityVotersPass());
        $container->addCompilerPass(new ConverterManagerPass());
        $container->addCompilerPass(new RoutingResolverPass());
        $container->addCompilerPass(new ProfilerPass());
        $container->addCompilerPass(new RegisterKernelListenersPass());
        $container->addCompilerPass(new TemplatingPass());
        $container->addCompilerPass(new AddConstraintValidatorsPass());
        $container->addCompilerPass(new AddFieldFactoryGuessersPass());
        $container->addCompilerPass(new AddClassesToCachePass());
        $container->addCompilerPass(new TranslatorPass());
        $container->addCompilerPass(new AddCacheWarmerPass());
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace()
    {
        return __NAMESPACE__;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        return strtr(__DIR__, '\\', '/');
    }
}
