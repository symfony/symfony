<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests;

use Symfony\Bridge\Twig\TwigEngine;

class TwigEngineTest extends TestCase
{
    public function testExistsWithTemplateInstances()
    {
        $engine = $this->getTwig();

        $this->assertTrue($engine->exists($this->getMockForAbstractClass('Twig_Template', array(), '', false)));
    }

    public function testExistsWithNonExistentTemplates()
    {
        $engine = $this->getTwig();

        $this->assertFalse($engine->exists('foobar'));
    }

    public function testExistsWithTemplateWithSyntaxErrors()
    {
        $engine = $this->getTwig();

        $this->assertTrue($engine->exists('error'));
    }

    public function testExists()
    {
        $engine = $this->getTwig();

        $this->assertTrue($engine->exists('index'));
    }

    protected function getTwig()
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array(
            'index' => 'foo',
            'error' => '{{ foo }',
        )));
        $parser = $this->getMock('Symfony\Component\Templating\TemplateNameParserInterface');

        return new TwigEngine($twig, $parser);
    }
}
