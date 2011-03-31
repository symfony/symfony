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
use Symfony\Component\Form\Renderer\Theme\TwigThemeFactory;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Translator;

class TwigThemeTest extends AbstractThemeTest
{
    protected function createThemeFactory()
    {
        $loader = new \Twig_Loader_Filesystem(__DIR__ . '/../../Resources/views/');
        $environment = new \Twig_Environment($loader, array(
          'cache' => false,
          'debug' => true
        ));
        $environment->addExtension(new TranslationExtension(new Translator('en', new MessageSelector())));

        return new TwigThemeFactory($environment, 'div_plain_layout.html.twig');
    }
}