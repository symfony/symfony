<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Form;

use Symfony\Tests\Component\Form\Renderer\Theme\AbstractThemeTest;
use Symfony\Bundle\TwigBundle\Form\TwigTheme;
use Symfony\Bundle\TwigBundle\Extension\TransExtension;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Translator;

class TwigThemeTest extends AbstractThemeTest
{
    protected function createTheme()
    {
        $loader = new \Twig_Loader_Filesystem(__DIR__ . '/../../Resources/views/');
        $environment = new \Twig_Environment($loader, array(
          'cache' => false,
          'debug' => true
        ));
        $environment->addExtension(new TransExtension(new Translator('en', new MessageSelector())));

        return new TwigTheme($environment, 'div_plain_layout.html.twig');
    }
}