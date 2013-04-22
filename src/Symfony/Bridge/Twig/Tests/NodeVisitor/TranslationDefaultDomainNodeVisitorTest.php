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

use Symfony\Bridge\Twig\NodeVisitor\TranslationDefaultDomainNodeVisitor;
use Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor;
use Symfony\Bridge\Twig\Tests\TestCase;

class TranslationDefaultDomainNodeVisitorTest extends TestCase
{
    private static $message = 'message';
    private static $domain = 'domain';

    /** @dataProvider getDefaultDomainAssignmentTestData */
    public function testDefaultDomainAssignment(\Twig_Node $node)
    {
        $env = new \Twig_Environment(new \Twig_Loader_String(), array('cache' => false, 'autoescape' => false, 'optimizations' => 0));
        $visitor = new TranslationDefaultDomainNodeVisitor();

        // visit trans_default_domain tag
        $defaultDomain = TwigNodeProvider::getTransDefaultDomainTag(self::$domain);
        $visitor->enterNode($defaultDomain, $env);
        $visitor->leaveNode($defaultDomain, $env);

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

        $this->assertEquals(array(array(self::$message, self::$domain)), $visitor->getMessages());
    }

    /** @dataProvider getDefaultDomainAssignmentTestData */
    public function testNewModuleWithoutDefaultDomainTag(\Twig_Node $node)
    {
        $env = new \Twig_Environment(new \Twig_Loader_String(), array('cache' => false, 'autoescape' => false, 'optimizations' => 0));
        $visitor = new TranslationDefaultDomainNodeVisitor();

        // visit trans_default_domain tag
        $newModule = TwigNodeProvider::getModule('test');
        $visitor->enterNode($newModule, $env);
        $visitor->leaveNode($newModule, $env);

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

        $this->assertEquals(array(array(self::$message, null)), $visitor->getMessages());
    }

    public function getDefaultDomainAssignmentTestData()
    {
        return array(
            array(TwigNodeProvider::getTransFilter(self::$message)),
            array(TwigNodeProvider::getTransChoiceFilter(self::$message)),
            array(TwigNodeProvider::getTransTag(self::$message)),
        );
    }
}
