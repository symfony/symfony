<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Node;

use Symfony\Bridge\Twig\Tests\TestCase;
use Symfony\Bridge\Twig\TokenParser\FormThemeTokenParser;
use Symfony\Bridge\Twig\Node\FormThemeNode;

class FormThemeTokenParserTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (version_compare(\Twig_Environment::VERSION, '1.5.0', '<')) {
            $this->markTestSkipped('Requires Twig version to be at least 1.5.0.');
        }
    }

    /**
     * @dataProvider getTestsForFormTheme
     */
    public function testCompile($source, $expected)
    {
        $env = new \Twig_Environment(new \Twig_Loader_String(), array('cache' => false, 'autoescape' => false, 'optimizations' => 0));
        $env->addTokenParser(new FormThemeTokenParser());
        $stream = $env->tokenize($source);
        $parser = new \Twig_Parser($env);

        $this->assertEquals($expected, $parser->parse($stream)->getNode('body')->getNode(0));
    }

    public function getTestsForFormTheme()
    {
        return array(
            array(
                '{% form_theme form "tpl1" %}',
                new FormThemeNode(
                    new \Twig_Node_Expression_Name('form', 1),
                    new \Twig_Node_Expression_Array(array(
                        new \Twig_Node_Expression_Constant(0, 1),
                        new \Twig_Node_Expression_Constant('tpl1', 1),
                    ), 1),
                    1,
                    'form_theme'
                )
            ),
            array(
                '{% form_theme form "tpl1" "tpl2" %}',
                new FormThemeNode(
                    new \Twig_Node_Expression_Name('form', 1),
                    new \Twig_Node_Expression_Array(array(
                        new \Twig_Node_Expression_Constant(0, 1),
                        new \Twig_Node_Expression_Constant('tpl1', 1),
                        new \Twig_Node_Expression_Constant(1, 1),
                        new \Twig_Node_Expression_Constant('tpl2', 1)
                    ), 1),
                    1,
                    'form_theme'
                )
            ),
            array(
                '{% form_theme form with "tpl1" %}',
                new FormThemeNode(
                    new \Twig_Node_Expression_Name('form', 1),
                    new \Twig_Node_Expression_Constant('tpl1', 1),
                    1,
                    'form_theme'
                )
            ),
            array(
                '{% form_theme form with ["tpl1"] %}',
                new FormThemeNode(
                    new \Twig_Node_Expression_Name('form', 1),
                    new \Twig_Node_Expression_Array(array(
                        new \Twig_Node_Expression_Constant(0, 1),
                        new \Twig_Node_Expression_Constant('tpl1', 1),
                    ), 1),
                    1,
                    'form_theme'
                )
            ),
            array(
                '{% form_theme form with ["tpl1", "tpl2"] %}',
                new FormThemeNode(
                    new \Twig_Node_Expression_Name('form', 1),
                    new \Twig_Node_Expression_Array(array(
                        new \Twig_Node_Expression_Constant(0, 1),
                        new \Twig_Node_Expression_Constant('tpl1', 1),
                        new \Twig_Node_Expression_Constant(1, 1),
                        new \Twig_Node_Expression_Constant('tpl2', 1)
                    ), 1),
                    1,
                    'form_theme'
                )
            ),
        );
    }
}
