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

use Symfony\Component\Form\Renderer\Theme\TwigThemeFactory;

class TwigThemeFactoryTest extends \PHPUnit_Framework_TestCase
{
    private $factory;

    protected function setUp()
    {
        $environment = $this->getMockBuilder('Twig_Environment')
            ->disableOriginalConstructor()
            ->getMock();
        $this->factory = new TwigThemeFactory($environment);
    }

    public function testCreate()
    {
        $theme = $this->factory->create('template');

        $this->assertInstanceOf('Symfony\Component\Form\Renderer\Theme\TwigTheme', $theme);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testCreateRequiresParams()
    {
        $this->factory->create();
    }
}