<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\AcceptHeaderItem;

class AcceptHeaderItemTest extends TestCase
{
    /**
     * @dataProvider provideFromStringData
     */
    public function testFromString($string, $value, array $attributes)
    {
        $item = AcceptHeaderItem::fromString($string);
        self::assertEquals($value, $item->getValue());
        self::assertEquals($attributes, $item->getAttributes());
    }

    public function provideFromStringData()
    {
        return [
            [
                'text/html',
                'text/html', [],
            ],
            [
                '"this;should,not=matter"',
                'this;should,not=matter', [],
            ],
            [
                "text/plain; charset=utf-8;param=\"this;should,not=matter\";\tfootnotes=true",
                'text/plain', ['charset' => 'utf-8', 'param' => 'this;should,not=matter', 'footnotes' => 'true'],
            ],
            [
                '"this;should,not=matter";charset=utf-8',
                'this;should,not=matter', ['charset' => 'utf-8'],
            ],
        ];
    }

    /**
     * @dataProvider provideToStringData
     */
    public function testToString($value, array $attributes, $string)
    {
        $item = new AcceptHeaderItem($value, $attributes);
        self::assertEquals($string, (string) $item);
    }

    public function provideToStringData()
    {
        return [
            [
                'text/html', [],
                'text/html',
            ],
            [
                'text/plain', ['charset' => 'utf-8', 'param' => 'this;should,not=matter', 'footnotes' => 'true'],
                'text/plain; charset=utf-8; param="this;should,not=matter"; footnotes=true',
            ],
        ];
    }

    public function testValue()
    {
        $item = new AcceptHeaderItem('value', []);
        self::assertEquals('value', $item->getValue());

        $item->setValue('new value');
        self::assertEquals('new value', $item->getValue());

        $item->setValue(1);
        self::assertEquals('1', $item->getValue());
    }

    public function testQuality()
    {
        $item = new AcceptHeaderItem('value', []);
        self::assertEquals(1.0, $item->getQuality());

        $item->setQuality(0.5);
        self::assertEquals(0.5, $item->getQuality());

        $item->setAttribute('q', 0.75);
        self::assertEquals(0.75, $item->getQuality());
        self::assertFalse($item->hasAttribute('q'));
    }

    public function testAttribute()
    {
        $item = new AcceptHeaderItem('value', []);
        self::assertEquals([], $item->getAttributes());
        self::assertFalse($item->hasAttribute('test'));
        self::assertNull($item->getAttribute('test'));
        self::assertEquals('default', $item->getAttribute('test', 'default'));

        $item->setAttribute('test', 'value');
        self::assertEquals(['test' => 'value'], $item->getAttributes());
        self::assertTrue($item->hasAttribute('test'));
        self::assertEquals('value', $item->getAttribute('test'));
        self::assertEquals('value', $item->getAttribute('test', 'default'));
    }
}
