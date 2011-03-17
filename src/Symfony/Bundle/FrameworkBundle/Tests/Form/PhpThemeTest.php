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

/**
 * Test theme template files shipped with framework bundle.
 */
class PhpThemeTest extends TestCase
{
    private $engine;
    private $theme;

    public function setUp()
    {
        $parser = new \Symfony\Component\Templating\TemplateNameParser();
        $loader = new \Symfony\Component\Templating\Loader\FilesystemLoader(__DIR__ . '/../../Resources/views/Form/%name%');
        $this->engine = new \Symfony\Component\Templating\PhpEngine($parser, $loader, array());
        $this->theme = new \Symfony\Component\Form\Renderer\Theme\PhpTheme($this->engine);
    }

    public function testTextWidget()
    {
        $field = new \Symfony\Component\Form\Field('foo');
        $plugin = new \Symfony\Component\Form\Renderer\Plugin\FieldPlugin($field);
        $field->setRenderer(new \Symfony\Component\Form\Renderer\DefaultRenderer($this->theme, 'text'));
        $field->setRendererVar('max_length', 128);
        $field->addRendererPlugin($plugin);
        $plugin->setUp($field->getRenderer());

        echo $field->getRenderer()->getWidget();
    }
}