<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NumericNode;

class IntegerNodeTest extends TestCase
{
    #[DataProvider('getValidValues')]
    public function testNormalize(int $value)
    {
        $node = new IntegerNode('test');
        $this->assertSame($value, $node->normalize($value));
    }

    #[DataProvider('getValidValues')]
    public function testValidNonEmptyValues(int $value)
    {
        $node = new IntegerNode('test');
        $node->setAllowEmptyValue(false);

        $this->assertSame($value, $node->finalize($value));
    }

    public static function getValidValues(): array
    {
        return [
            [1798],
            [-678],
            [0],
        ];
    }

    #[DataProvider('getInvalidValues')]
    public function testNormalizeThrowsExceptionOnInvalidValues($value)
    {
        $node = new IntegerNode('test');

        $this->expectException(InvalidTypeException::class);

        $node->normalize($value);
    }

    public static function getInvalidValues(): array
    {
        return [
            [null],
            [''],
            ['foo'],
            [true],
            [false],
            [0.0],
            [0.1],
            [[]],
            [['foo' => 'bar']],
            [new \stdClass()],
        ];
    }

    public function testFinalizeAcceptsEnvPlaceholderBelowMin()
    {
        $node = new IntegerNode('max_retries', null, 1);
        NumericNode::setPlaceholder('env_BAR', ['int' => 0]);

        try {
            $this->assertSame('env_BAR', $node->finalize('env_BAR'));
        } finally {
            NumericNode::resetPlaceholders();
        }
    }
}
