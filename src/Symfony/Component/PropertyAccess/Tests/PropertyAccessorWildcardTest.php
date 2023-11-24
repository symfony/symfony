<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass;

class PropertyAccessorWildcardTest extends TestCase
{
    private PropertyAccessor $propertyAccessor;

    protected function setUp(): void
    {
        $this->propertyAccessor = new PropertyAccessor();
    }

    private const TEST_ARRAY = [
        [
            'id' => 1,
            'name' => 'John',
            'languages' => ['EN'],
            '*' => 'wildcard1',
            'jobs' => [
                [
                    'title' => 'chef',
                    'info' => [
                        'experience' => 6,
                        'salary' => 34,
                    ],
                ],
                [
                    'title' => 'waiter',
                    'info' => [
                        'experience' => 3,
                        'salary' => 30,
                    ],
                ],
            ],
            'info' => [
                'age' => 32,
            ],
        ],
        [
            'id' => 2,
            'name' => 'Luke',
            'languages' => ['EN', 'FR'],
            '*' => 'wildcard2',
            'jobs' => [
                [
                    'title' => 'chef',
                    'info' => [
                        'experience' => 3,
                        'salary' => 31,
                    ],
                ],
                [
                    'title' => 'bartender',
                    'info' => [
                        'experience' => 6,
                        'salary' => 30,
                    ],
                ],
            ],
            'info' => [
                'age' => 28,
            ],
        ],
    ];

    public static function provideWildcardPaths(): iterable
    {
        yield [
            'path' => '[*][id]',
            'expected' => [1, 2],
        ];

        yield [
            'path' => '[*][name]',
            'expected' => ['John', 'Luke'],
        ];

        yield [
            'path' => '[*][languages]',
            'expected' => ['EN', 'EN', 'FR'],
        ];

        yield [
            'path' => '[*][info][age]',
            'expected' => [32, 28],
        ];

        yield [
            'path' => '[0][jobs][*][title]',
            'expected' => ['chef', 'waiter'],
        ];

        yield [
            'path' => '[0][jobs][*][info]',
            'expected' => [
                ['experience' => 6, 'salary' => 34],
                ['experience' => 3, 'salary' => 30],
            ],
        ];

        yield [
            'path' => '[0][jobs][*][info][experience]',
            'expected' => [6, 3],
        ];

        yield [
            'path' => '[*][jobs][0][title]',
            'expected' => ['chef', 'chef'],
        ];

        yield [
            'path' => '[*][jobs][*][title]',
            'expected' => ['chef', 'waiter', 'chef', 'bartender'],
        ];

        yield [
            'path' => '[*][jobs][*][info][*]',
            'expected' => [6, 34, 3, 30, 3, 31, 6, 30],
        ];

        yield [
            'path' => '[*][jobs][*][info]',
            'expected' => [
                ['experience' => 6, 'salary' => 34],
                ['experience' => 3, 'salary' => 30],
                ['experience' => 3, 'salary' => 31],
                ['experience' => 6, 'salary' => 30],
            ],
        ];

        yield [
            'path' => '[0][\*]',
            'expected' => 'wildcard1',
        ];

        yield [
            'path' => '[*][\*]',
            'expected' => ['wildcard1', 'wildcard2'],
        ];
    }

    /**
     * @dataProvider provideWildcardPaths
     */
    public function testAccessorWithWildcard(string $path, string|array $expected)
    {
        self::assertSame($expected, $this->propertyAccessor->getValue(self::TEST_ARRAY, $path));
    }

    public function testAccessorWithWildcardAndObject()
    {
        $array = self::TEST_ARRAY;

        $array[0]['class'] = new TestClass('foo');
        $array[1]['class'] = new TestClass('bar');

        self::assertSame(['foo', 'bar'], $this->propertyAccessor->getValue($array, '[*][class].publicAccessor'));

        $array[0]['classes'] = [
            new TestClass('foo'),
            new TestClass('bar'),
        ];
        $array[1]['classes'] = [
            new TestClass('baz'),
            new TestClass('qux'),
        ];

        self::assertSame(['foo', 'bar', 'baz', 'qux'], $this->propertyAccessor->getValue($array, '[*][classes][*].publicAccessor'));
    }
}
