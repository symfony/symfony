<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests\Node;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Compiler;

abstract class AbstractNodeTest extends TestCase
{
    /**
     * @dataProvider getEvaluateData
     */
    public function testEvaluate($expected, $node, $variables = array(), $functions = array()): void
    {
        $this->assertSame($expected, $node->evaluate($functions, $variables));
    }

    abstract public function getEvaluateData(): void;

    /**
     * @dataProvider getCompileData
     */
    public function testCompile($expected, $node, $functions = array()): void
    {
        $compiler = new Compiler($functions);
        $node->compile($compiler);
        $this->assertSame($expected, $compiler->getSource());
    }

    abstract public function getCompileData(): void;

    /**
     * @dataProvider getDumpData
     */
    public function testDump($expected, $node): void
    {
        $this->assertSame($expected, $node->dump());
    }

    abstract public function getDumpData(): void;
}
