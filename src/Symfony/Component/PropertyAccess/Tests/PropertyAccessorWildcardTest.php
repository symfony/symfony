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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\Tests\Fixtures\NonTraversableArrayObject;

class PropertyAccessorWildcardTest extends TestCase
{
    private const PEOPLE = [
        [
            'name' => 'Ada',
            'languages' => ['English', 'French'],
            'jobs' => [
                ['title' => 'programmer'],
                ['title' => 'writer'],
            ],
        ],
        [
            'name' => 'Grace',
            'languages' => ['English'],
            'jobs' => [
                ['title' => 'computer scientist'],
            ],
        ],
    ];

    private PropertyAccessorInterface $accessor;

    protected function setUp(): void
    {
        $this->accessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableWildcardReads()
            ->getPropertyAccessor();
    }

    public function testWildcardsAreDisabledByDefault()
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        $this->assertSame('star', $accessor->getValue(['*' => 'star'], '[*]'));
        $this->assertSame('escaped', $accessor->getValue(['\*' => 'escaped'], '[\*]'));
    }

    public function testWildcardPathIsWritableByDefault()
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $data = [];
        $accessor->setValue($data, '[*]', 'star');

        $this->assertSame(['*' => 'star'], $data);
        $this->assertTrue($accessor->isWritable($data, '[*]'));
    }

    #[DataProvider('provideWildcardPaths')]
    public function testGetValue(string $path, mixed $expected)
    {
        $this->assertSame($expected, $this->accessor->getValue(self::PEOPLE, $path));
    }

    public static function provideWildcardPaths(): iterable
    {
        yield 'one entry per element' => ['[*][name]', ['Ada', 'Grace']];
        yield 'nested arrays are kept nested' => ['[*][languages]', [['English', 'French'], ['English']]];
        yield 'chained wildcards nest' => ['[*][jobs][*][title]', [['programmer', 'writer'], ['computer scientist']]];
        yield 'a trailing wildcard returns the elements' => ['[0][jobs][*]', [['title' => 'programmer'], ['title' => 'writer']]];
        yield 'a wildcard on a nested collection' => ['[0][jobs][*][title]', ['programmer', 'writer']];
    }

    public function testTheResultShapeDoesNotDependOnTheData()
    {
        $lists = [['tags' => ['a', 'b']], ['tags' => ['c']]];
        $mixed = [['tags' => ['a', 'b']], ['tags' => 'c']];

        $this->assertSame([['a', 'b'], ['c']], $this->accessor->getValue($lists, '[*][tags]'));
        $this->assertSame([['a', 'b'], 'c'], $this->accessor->getValue($mixed, '[*][tags]'));
    }

    public function testGetValueKeepsTheIterationOrderAndDropsTheKeys()
    {
        $people = ['ada' => ['name' => 'Ada'], 'grace' => ['name' => 'Grace']];

        $this->assertSame(['Ada', 'Grace'], $this->accessor->getValue($people, '[*][name]'));
    }

    public function testGetValueReadsPropertiesAfterTheWildcard()
    {
        $people = [(object) ['name' => 'Ada'], (object) ['name' => 'Grace']];

        $this->assertSame(['Ada', 'Grace'], $this->accessor->getValue($people, '[*].name'));
    }

    public function testGetValueFromArrayObject()
    {
        $people = new \ArrayObject([['name' => 'Ada'], ['name' => 'Grace']]);

        $this->assertSame(['Ada', 'Grace'], $this->accessor->getValue($people, '[*][name]'));
    }

    public function testGetValueFromTraversableWithoutArrayAccess()
    {
        $people = new class implements \IteratorAggregate {
            public function getIterator(): \Iterator
            {
                yield ['name' => 'Ada'];
                yield ['name' => 'Grace'];
            }
        };

        $this->assertSame(['Ada', 'Grace'], $this->accessor->getValue($people, '[*][name]'));
    }

    public function testGetValueFromNonIterableValueThrows()
    {
        $this->expectException(NoSuchIndexException::class);
        $this->expectExceptionMessage('Cannot expand the wildcard in path "[*][name]" because the value of type "Symfony\Component\PropertyAccess\Tests\Fixtures\NonTraversableArrayObject" is not iterable.');

        $this->accessor->getValue(new NonTraversableArrayObject(self::PEOPLE), '[*][name]');
    }

    public function testGetValueFromEmptyCollection()
    {
        $this->assertSame([], $this->accessor->getValue([], '[*][name]'));
    }

    public function testGetValueWithAMissingIndexUnderTheWildcard()
    {
        $people = [['name' => 'Ada'], ['nickname' => 'Amazing Grace']];

        $this->assertSame(['Ada', null], $this->accessor->getValue($people, '[*][name]'));
    }

    public function testGetValueWithAMissingIndexUnderTheWildcardThrowsWhenConfiguredTo()
    {
        $accessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableWildcardReads()
            ->enableExceptionOnInvalidIndex()
            ->getPropertyAccessor();

        $this->expectException(NoSuchIndexException::class);

        $accessor->getValue([['name' => 'Ada'], ['nickname' => 'Amazing Grace']], '[*][name]');
    }

    public function testGetValueThroughAScalarUnderTheWildcardThrows()
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->accessor->getValue(self::PEOPLE, '[*][name][first]');
    }

    public function testGetValueOfAnEscapedWildcard()
    {
        $this->assertSame('star', $this->accessor->getValue(['*' => 'star'], '[\*]'));
        $this->assertSame(['star', 'asterisk'], $this->accessor->getValue([['*' => 'star'], ['*' => 'asterisk']], '[*][\*]'));
    }

    public function testAnEscapedWildcardRoundTrips()
    {
        $data = [];
        $this->accessor->setValue($data, '[\*]', 'star');

        $this->assertSame(['*' => 'star'], $data);
        $this->assertSame('star', $this->accessor->getValue($data, '[\*]'));
        $this->assertTrue($this->accessor->isWritable($data, '[\*]'));
    }

    public function testAnEscapedBackslashBeforeAWildcardRoundTrips()
    {
        $data = [];
        $this->accessor->setValue($data, '[\\\\*]', 'backslash-star');

        $this->assertSame(['\\*' => 'backslash-star'], $data);
        $this->assertSame('backslash-star', $this->accessor->getValue($data, '[\\\\*]'));
        $this->assertTrue($this->accessor->isWritable($data, '[\\\\*]'));
    }

    public function testSetValueThroughAWildcardThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot write to the property path "[*][name]" because it contains a wildcard.');

        $people = self::PEOPLE;
        $this->accessor->setValue($people, '[*][name]', 'Ada');
    }

    public function testIsWritableThroughAWildcard()
    {
        $this->assertFalse($this->accessor->isWritable(self::PEOPLE, '[*][name]'));
    }

    public function testIsReadableThroughAWildcard()
    {
        $this->assertTrue($this->accessor->isReadable(self::PEOPLE, '[*][name]'));
        $this->assertFalse($this->accessor->isReadable(new NonTraversableArrayObject(self::PEOPLE), '[*][name]'));
    }
}
