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
use Symfony\Bridge\Twig\NodeVisitor\TranslationDefaultDomainNodeVisitor;
use Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor;
use Twig\Environment;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Node;

class TranslationDefaultDomainNodeVisitorTest extends TestCase
{
    private const MESSAGE = 'message';
    private const DOMAIN = 'domain';

    /** @dataProvider getDefaultDomainAssignmentTestData */
    public function testDefaultDomainAssignment(Node $node)
    {
        $env = new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock(), ['cache' => false, 'autoescape' => false, 'optimizations' => 0]);
        $visitor = new TranslationDefaultDomainNodeVisitor();

        // visit trans_default_domain tag
        $defaultDomain = TwigNodeProvider::getTransDefaultDomainTag(self::DOMAIN);
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

        $this->assertEquals([[self::MESSAGE, self::DOMAIN]], $visitor->getMessages());
    }

    /** @dataProvider getDefaultDomainAssignmentTestData */
    public function testNewModuleWithoutDefaultDomainTag(Node $node)
    {
        $env = new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock(), ['cache' => false, 'autoescape' => false, 'optimizations' => 0]);
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

        $this->assertEquals([[self::MESSAGE, null]], $visitor->getMessages());
    }

    public function getDefaultDomainAssignmentTestData()
    {
        return [
            [TwigNodeProvider::getTransFilter(self::MESSAGE)],
            [TwigNodeProvider::getTransTag(self::MESSAGE)],
            // with named arguments
            [TwigNodeProvider::getTransFilter(self::MESSAGE, null, [
                'arguments' => new ArrayExpression([], 0),
            ])],
        ];
    }
}
