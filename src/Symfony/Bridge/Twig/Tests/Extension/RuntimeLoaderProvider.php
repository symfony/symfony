<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Component\Form\FormRenderer;
use Twig\Environment;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

trait RuntimeLoaderProvider
{
    protected function registerTwigRuntimeLoader(Environment $environment, FormRenderer $renderer)
    {
        $loader = $this->createMock(RuntimeLoaderInterface::class);
        $loader->expects($this->any())->method('load')->willReturnMap([
            ['Symfony\Component\Form\FormRenderer', $renderer],
        ]);
        $environment->addRuntimeLoader($loader);
    }
}
