<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Emoji\EmojiTransliterator;
use Symfony\Component\ErrorHandler\ErrorRenderer\CliErrorRenderer;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Yaml\Yaml;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class ErrorRendererPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // in the 'test' environment, use the CLI error renderer as the default one
        if ($container->hasDefinition('test.client')) {
            $container->getDefinition('twig.error_renderer.html')
                ->setArgument(1, new Definition(CliErrorRenderer::class));
        }
    }
}
