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

use Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor;
use Symfony\Bridge\Twig\Tests\TestCase;

class TranslationNodeVisitorTest extends TestCase
{
    /** @dataProvider getMessagesExtractionTestData */
    public function testMessagesExtraction(\Twig_Node $node, array $expectedMessages)
    {
        $env = new \Twig_Environment($this->getMock('Twig_LoaderInterface'), array('cache' => false, 'autoescape' => false, 'optimizations' => 0));
        $visitor = new TranslationNodeVisitor();
        $visitor->enable();
        $visitor->enterNode($node, $env);
        $visitor->leaveNode($node, $env);
        $this->assertEquals($expectedMessages, $visitor->getMessages());
    }

    public function testMessageExtractionWithInvalidDomainNode()
    {
        $message = 'new key';

        $node = new \Twig_Node_Expression_Filter(
            new \Twig_Node_Expression_Constant($message, 0),
            new \Twig_Node_Expression_Constant('trans', 0),
            new \Twig_Node(array(
                new \Twig_Node_Expression_Array(array(), 0),
                new \Twig_Node_Expression_Name('variable', 0),
            )),
            0
        );

        $this->testMessagesExtraction($node, array(array($message, TranslationNodeVisitor::UNDEFINED_DOMAIN)));
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
