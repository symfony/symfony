<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Test\Traits;

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormRenderer;
use Twig\Environment;
use Twig\RuntimeLoader\ContainerRuntimeLoader;

trait RuntimeLoaderProvider
{
    protected function registerTwigRuntimeLoader(Environment $environment, FormRenderer $renderer): void
    {
        $environment->addRuntimeLoader(new ContainerRuntimeLoader(new ServiceLocator([
            FormRenderer::class => static fn () => $renderer,
        ])));
    }
}
