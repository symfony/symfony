<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\NodeVisitor;

use Symfony\Bridge\Twig\NodeVisitor\TranslationNamespaceNodeVisitor;
use Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor;

class TranslationNamespaceNodeVisitorTest extends \PHPUnit_Framework_TestCase
{
    private static $namespace = 'foo';
    private static $message = '.message';

    /** @dataProvider getNamespaceAssignmentTestData */
    public function testNamespaceAssignment(\Twig_Node $node)
    {
        $env = new \Twig_Environment($this->getMock('Twig_LoaderInterface'), array('cache' => false, 'autoescape' => false, 'optimizations' => 0));
        $visitor = new TranslationNamespaceNodeVisitor();

        // visit trans_namespace tag
        $namespace = TwigNodeProvider::getTransNamespaceTag(self::$namespace);
        $visitor->enterNode($namespace, $env);
        $visitor->leaveNode($namespace, $env);

        // visit tested node
        $enteredNode = $visitor->enterNode($node, $env);
        $leavedNode = $visitor->leaveNode($node, $env);
        $this->assertSame($node, $enteredNode);
        $this->assertSame($node, $leavedNode);

        // extracting tested node messages
        $visitor = new TranslationNodeVisitor();
        $visitor->enable();
        $visitor->enterNode($node, $env);
        $visitor->leaveNode($node, $env);

        $this->assertEquals([[self::$namespace.self::$message, null]], $visitor->getMessages());
    }

    public function getNamespaceAssignmentTestData()
    {
        return array(
            array(TwigNodeProvider::getTransFilter(self::$message)),
            array(TwigNodeProvider::getTransChoiceFilter(self::$message)),
            array(TwigNodeProvider::getTransTag(self::$message)),
            // with named arguments
            array(TwigNodeProvider::getTransFilter(self::$message, null, array(
                'arguments' => new \Twig_Node_Expression_Array(array(), 0),
            ))),
            array(TwigNodeProvider::getTransChoiceFilter(self::$message), null, array(
                'arguments' => new \Twig_Node_Expression_Array(array(), 0),
            )),
        );
    }
}
