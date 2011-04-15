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

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddConstraintValidatorsPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddFieldFactoryGuessersPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TemplatingPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RegisterKernelListenersPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RoutingResolverPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ProfilerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddClassesToCachePass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddClassesToAutoloadMapPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslatorPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddCacheWarmerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ContainerBuilderDebugDumpPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CompilerDebugDumpPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Scope;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\ClassLoader\ClassCollectionLoader;
use Symfony\Component\ClassLoader\MapFileClassLoader;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FrameworkBundle extends Bundle
{
    /**
     * Boots the Bundle.
     */
    public function boot()
    {
        // load core classes
        ClassCollectionLoader::load(
            $this->container->getParameter('kernel.compiled_classes'),
            $this->container->getParameter('kernel.cache_dir'),
            'classes',
            $this->container->getParameter('kernel.debug'),
            true
        );

        if ($this->container->has('error_handler')) {
            $this->container->get('error_handler');
        }

        if ($this->container->hasParameter('document_root')) {
            File::setDocumentRoot($this->container->getParameter('document_root'));
        }

        if (file_exists($this->container->getParameter('kernel.cache_dir').'/autoload.php')) {
            $classloader = new MapFileClassLoader($this->container->getParameter('kernel.cache_dir').'/autoload.php');
            $classloader->register(true);
        }
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addScope(new Scope('request'));

        $container->addCompilerPass(new RoutingResolverPass());
        $container->addCompilerPass(new ProfilerPass());
        $container->addCompilerPass(new RegisterKernelListenersPass());
        $container->addCompilerPass(new TemplatingPass());
        $container->addCompilerPass(new AddConstraintValidatorsPass());
        $container->addCompilerPass(new AddFieldFactoryGuessersPass());
        $container->addCompilerPass(new AddClassesToCachePass());
        $container->addCompilerPass(new AddClassesToAutoloadMapPass());
        $container->addCompilerPass(new TranslatorPass());
        $container->addCompilerPass(new AddCacheWarmerPass());

        if ($container->getParameter('kernel.debug')) {
            $container->addCompilerPass(new ContainerBuilderDebugDumpPass(), PassConfig::TYPE_BEFORE_REMOVING);
            $container->addCompilerPass(new CompilerDebugDumpPass(), PassConfig::TYPE_AFTER_REMOVING);
        }
    }
}
