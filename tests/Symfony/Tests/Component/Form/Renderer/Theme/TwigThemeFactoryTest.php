<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Renderer\Theme;

use Symfony\Component\Form\Renderer\Theme\TwigTheme;
use Symfony\Component\Form\Renderer\Theme\TwigThemeFactory;

class TwigThemeFactoryTest extends \PHPUnit_Framework_TestCase
{
    private $environment;

    private $factory;

    protected function setUp()
    {
        $this->environment = $this->getMockBuilder('Twig_Environment')
            ->disableOriginalConstructor()
            ->getMock();
        $this->factory = new TwigThemeFactory($this->environment);
    }

    public function testCreate()
    {
        $theme = $this->factory->create('template');

        $this->assertEquals(new TwigTheme($this->environment, 'template'), $theme);
    }

    public function testCreateWithTwigTemplate()
    {
        $template = $this->getMockBuilder('Twig_Template')
            ->disableOriginalConstructor()
            ->getMock();
        $theme = $this->factory->create($template);

        $this->assertEquals(new TwigTheme($this->environment, $template), $theme);
    }

    public function testCreateWithFallbacks()
    {
        $this->factory = new TwigThemeFactory($this->environment, 'fallback');

        $theme = $this->factory->create('template');

        $this->assertEquals(new TwigTheme($this->environment, array('fallback', 'template')), $theme);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testCreateRequiresParams()
    {
        $this->factory->create();
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testCreateRequiresStringOrTwigTemplate()
    {
        $this->factory->create(new \stdClass());
    }
}