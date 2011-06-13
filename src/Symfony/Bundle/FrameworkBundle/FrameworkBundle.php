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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\FormPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TemplatingPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RegisterKernelListenersPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RoutingResolverPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ProfilerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslatorPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddCacheWarmerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ContainerBuilderDebugDumpPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CompilerDebugDumpPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslationTemplateExtractorPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslationFileExtractorPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslationWriterPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Scope;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FrameworkBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addScope(new Scope('request'));

        $container->addCompilerPass(new RoutingResolverPass());
        $container->addCompilerPass(new ProfilerPass());
        $container->addCompilerPass(new RegisterKernelListenersPass());
        $container->addCompilerPass(new TemplatingPass());
        $container->addCompilerPass(new AddConstraintValidatorsPass());
        $container->addCompilerPass(new FormPass());
        $container->addCompilerPass(new TranslatorPass());
        $container->addCompilerPass(new AddCacheWarmerPass());
        $container->addCompilerPass(new TranslationTemplateExtractorPass());
        $container->addCompilerPass(new TranslationFileExtractorPass());
        $container->addCompilerPass(new TranslationWriterPass());

        if ($container->getParameter('kernel.debug')) {
            $container->addCompilerPass(new ContainerBuilderDebugDumpPass(), PassConfig::TYPE_AFTER_REMOVING);
            $container->addCompilerPass(new CompilerDebugDumpPass(), PassConfig::TYPE_AFTER_REMOVING);
        }
    }
}
