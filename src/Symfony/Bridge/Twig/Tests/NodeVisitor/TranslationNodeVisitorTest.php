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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor;
use Twig\Environment;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;

class TranslationNodeVisitorTest extends TestCase
{
    /** @dataProvider getMessagesExtractionTestData */
    public function testMessagesExtraction(Node $node, array $expectedMessages)
    {
        $env = new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock(), array('cache' => false, 'autoescape' => false, 'optimizations' => 0));
        $visitor = new TranslationNodeVisitor();
        $visitor->enable();
        $visitor->enterNode($node, $env);
        $visitor->leaveNode($node, $env);
        $this->assertEquals($expectedMessages, $visitor->getMessages());
    }

    public function testMessageExtractionWithInvalidDomainNode()
    {
        $message = 'new key';

        $node = new FilterExpression(
            new ConstantExpression($message, 0),
            new ConstantExpression('trans', 0),
            new Node(array(
                new ArrayExpression(array(), 0),
                new NameExpression('variable', 0),
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
