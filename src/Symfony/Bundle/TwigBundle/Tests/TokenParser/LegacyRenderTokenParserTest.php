<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\TokenParser;

use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\TokenParser\RenderTokenParser;
use Symfony\Bundle\TwigBundle\Node\RenderNode;

/**
 * @group legacy
 */
class LegacyRenderTokenParserTest extends TestCase
{
    /**
     * @dataProvider getTestsForRender
     */
    public function testCompile($source, $expected)
    {
        $env = new \Twig_Environment($this->getMock('Twig_LoaderInterface'), array('cache' => false, 'autoescape' => false, 'optimizations' => 0));
        $env->addTokenParser(new RenderTokenParser());
        $stream = $env->tokenize($source);
        $parser = new \Twig_Parser($env);

        $this->assertEquals($expected, $parser->parse($stream)->getNode('body')->getNode(0));
    }

    public function getTestsForRender()
    {
        return array(
            array(
                '{% render "foo" %}',
                new RenderNode(
                    new \Twig_Node_Expression_Constant('foo', 1),
                    new \Twig_Node_Expression_Array(array(), 1),
                    1,
                    'render'
                ),
            ),
            array(
                '{% render "foo", {foo: 1} %}',
                new RenderNode(
                    new \Twig_Node_Expression_Constant('foo', 1),
                    new \Twig_Node_Expression_Array(array(
                        new \Twig_Node_Expression_Constant('foo', 1),
                        new \Twig_Node_Expression_Constant('1', 1),
                    ), 1),
                    1,
                    'render'
                ),
            ),
        );
    }
}
