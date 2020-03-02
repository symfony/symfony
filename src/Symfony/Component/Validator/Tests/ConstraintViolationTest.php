<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;

class ConstraintViolationTest extends TestCase
{
    public function testToStringHandlesArrays()
    {
        $violation = new ConstraintViolation(
            'Array',
            '{{ value }}',
            ['{{ value }}' => [1, 2, 3]],
            'Root',
            'property.path',
            null
        );

        $expected = <<<'EOF'
Root.property.path:
    Array
EOF;

        $this->assertSame($expected, (string) $violation);
    }

    public function testToStringHandlesArrayRoots()
    {
        $violation = new ConstraintViolation(
            '42 cannot be used here',
            'this is the message template',
            [],
            ['some_value' => 42],
            'some_value',
            null
        );

        $expected = <<<'EOF'
Array.some_value:
    42 cannot be used here
EOF;

        $this->assertSame($expected, (string) $violation);
    }

    public function testToStringHandlesCodes()
    {
        $violation = new ConstraintViolation(
            '42 cannot be used here',
            'this is the message template',
            [],
            ['some_value' => 42],
            'some_value',
            null,
            null,
            0
        );

        $expected = <<<'EOF'
Array.some_value:
    42 cannot be used here (code 0)
EOF;

        $this->assertSame($expected, (string) $violation);
    }

    public function testToStringOmitsEmptyCodes()
    {
        $expected = <<<'EOF'
Array.some_value:
    42 cannot be used here
EOF;

        $violation = new ConstraintViolation(
            '42 cannot be used here',
            'this is the message template',
            [],
            ['some_value' => 42],
            'some_value',
            null,
            null,
            null
        );

        $this->assertSame($expected, (string) $violation);

        $violation = new ConstraintViolation(
            '42 cannot be used here',
            'this is the message template',
            [],
            ['some_value' => 42],
            'some_value',
            null,
            null,
            ''
        );

        $this->assertSame($expected, (string) $violation);
    }

    /**
     * @requires PHP 7
     */
    public function testPrivateToString()
    {
        if (\PHP_VERSION_ID >= 80000) {
            $this->markTestSkipped('__toString() must be public in PHP 8');
        }

        $this->expectException(\TypeError::class);
        new ConstraintViolation(
            new PrivateToString(),
            '',
            [],
            'Root',
            'property.path',
            null
        );
    }
}

if (\PHP_VERSION_ID < 80000) {
    @eval('
    namespace Symfony\Component\Validator\Tests;

    class PrivateToString
    {
        private function __toString()
        {
        }
    }
    ');
}
