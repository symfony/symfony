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
use Twig\Attribute\FirstClassTwigCallableReady;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\TwigFilter;

class TranslationNodeVisitorTest extends TestCase
{
    /** @dataProvider getMessagesExtractionTestData */
    public function testMessagesExtraction(Node $node, array $expectedMessages)
    {
        $env = new Environment($this->createMock(LoaderInterface::class), ['cache' => false, 'autoescape' => false, 'optimizations' => 0]);
        $visitor = new TranslationNodeVisitor();
        $visitor->enable();
        $visitor->enterNode($node, $env);
        $visitor->leaveNode($node, $env);
        $this->assertEquals($expectedMessages, $visitor->getMessages());
    }

    public function testMessageExtractionWithInvalidDomainNode()
    {
        $message = 'new key';

        if (class_exists(Nodes::class)) {
            $n = new Nodes([
                new ArrayExpression([], 0),
                new ContextVariable('variable', 0),
            ]);
        } else {
            $n = new Node([
                new ArrayExpression([], 0),
                new NameExpression('variable', 0),
            ]);
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            $node = new FilterExpression(
                new ConstantExpression($message, 0),
                new TwigFilter('trans'),
                $n,
                0
            );
        } else {
            $node = new FilterExpression(
                new ConstantExpression($message, 0),
                new ConstantExpression('trans', 0),
                $n,
                0
            );
        }

        $this->testMessagesExtraction($node, [[$message, TranslationNodeVisitor::UNDEFINED_DOMAIN]]);
    }

    public static function getMessagesExtractionTestData()
    {
        $message = 'new key';
        $domain = 'domain';

        return [
            [TwigNodeProvider::getTransFilter($message), [[$message, null]]],
            [TwigNodeProvider::getTransTag($message), [[$message, null]]],
            [TwigNodeProvider::getTransFilter($message, $domain), [[$message, $domain]]],
            [TwigNodeProvider::getTransTag($message, $domain), [[$message, $domain]]],
        ];
    }
}
