<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Tests\Extension;

use Symphony\Component\Form\FormRenderer;
use Twig\Environment;

trait RuntimeLoaderProvider
{
    protected function registerTwigRuntimeLoader(Environment $environment, FormRenderer $renderer)
    {
        $loader = $this->getMockBuilder('Twig\RuntimeLoader\RuntimeLoaderInterface')->getMock();
        $loader->expects($this->any())->method('load')->will($this->returnValueMap(array(
            array('Symphony\Component\Form\FormRenderer', $renderer),
        )));
        $environment->addRuntimeLoader($loader);
    }
}
