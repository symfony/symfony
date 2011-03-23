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

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Form\PhpTemplatingThemeEngine;
use Symfony\Component\Form\Type\AbstractFieldType;
use Symfony\Component\Form\FieldBuilder;
use Symfony\Component\Form\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\Form\Type\Loader\DefaultTypeLoader;
use Symfony\Component\Form\FormFactory;
use Symfony\Tests\Component\Form\Renderer\ThemeEngine\AbstractThemeEngineFunctionalTest;

/**
 * Test theme template files shipped with framework bundle.
 */
class PhpTemplatingThemeEngineFunctionalTest extends AbstractThemeEngineFunctionalTest
{
    protected function createEngine()
    {
        $parser = new \Symfony\Component\Templating\TemplateNameParser();
        $loader = new \Symfony\Component\Templating\Loader\FilesystemLoader(__DIR__ . '/../../Resources/views/Form/%name%');
        $engine = new \Symfony\Component\Templating\PhpEngine($parser, $loader, array());
        $engine->addHelpers(array(
            new \Symfony\Bundle\FrameworkBundle\Templating\Helper\TranslatorHelper(
                new \Symfony\Component\Translation\IdentityTranslator(
                    new \Symfony\Component\Translation\MessageSelector()
                )
            )
        ));
        return new PhpTemplatingThemeEngine($engine);
    }
}