<?php

namespace Symfony\Bridge\Twig\Tests\NodeVisitor;

use Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor;
use Symfony\Bridge\Twig\Tests\TestCase;

class TranslationNodeVisitorTest extends TestCase
{
    /** @dataProvider getMessagesExtractionTestData */
    public function testMessagesExtraction(\Twig_Node $node, array $expectedMessages)
    {
        $env = new \Twig_Environment(new \Twig_Loader_String(), array('cache' => false, 'autoescape' => false, 'optimizations' => 0));
        $visitor = new TranslationNodeVisitor();
        $visitor->enable();
        $visitor->enterNode($node, $env);
        $visitor->leaveNode($node, $env);
        $this->assertEquals($expectedMessages, $visitor->getMessages());
    }

    public function getMessagesExtractionTestData()
    {
        $message = 'new key';
        $domain = 'domain';

        return array(
            array(TwigNodeProvider::getTransFilter($message), array(array($message, null))),
            array(TwigNodeProvider::getTransChoiceFilter($message), array(array($message, null))),
            array(TwigNodeProvider::getTransTag($message), array(array($message, null))),
            array(TwigNodeProvider::getTransFilter($message, $domain), array(array($message, $domain))),
            array(TwigNodeProvider::getTransChoiceFilter($message, $domain), array(array($message, $domain))),
            array(TwigNodeProvider::getTransTag($message, $domain), array(array($message, $domain))),
        );
    }
}
