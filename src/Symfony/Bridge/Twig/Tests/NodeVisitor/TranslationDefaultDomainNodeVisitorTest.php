<?php

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
        $defaultDomain = TwigNodeProvider::getTransDefaultDomainTag('domain');
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

    public function getDefaultDomainAssignmentTestData()
    {
        return array(
            array(TwigNodeProvider::getTransFilter(self::$message, self::$domain)),
            array(TwigNodeProvider::getTransChoiceFilter(self::$message, self::$domain)),
            array(TwigNodeProvider::getTransTag(self::$message, self::$domain)),
        );
    }
}
