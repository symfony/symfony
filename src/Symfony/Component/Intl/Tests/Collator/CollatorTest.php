<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Collator;

use Symfony\Component\Intl\Collator\Collator;
use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException;
use Symfony\Component\Intl\Exception\MethodNotImplementedException;
use Symfony\Component\Intl\Globals\IntlGlobals;

/**
 * @group legacy
 */
class CollatorTest extends AbstractCollatorTest
{
    public function testConstructorWithUnsupportedLocale()
    {
        self::expectException(MethodArgumentValueNotImplementedException::class);
        $this->getCollator('pt_BR');
    }

    public function testCompare()
    {
        self::expectException(MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->compare('a', 'b');
    }

    public function testGetAttribute()
    {
        self::expectException(MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->getAttribute(Collator::NUMERIC_COLLATION);
    }

    public function testGetErrorCode()
    {
        $collator = $this->getCollator('en');
        self::assertEquals(IntlGlobals::U_ZERO_ERROR, $collator->getErrorCode());
    }

    public function testGetErrorMessage()
    {
        $collator = $this->getCollator('en');
        self::assertEquals('U_ZERO_ERROR', $collator->getErrorMessage());
    }

    public function testGetLocale()
    {
        $collator = $this->getCollator('en');
        self::assertEquals('en', $collator->getLocale());
    }

    public function testConstructWithoutLocale()
    {
        $collator = $this->getCollator(null);
        self::assertInstanceOf(Collator::class, $collator);
    }

    public function testGetSortKey()
    {
        self::expectException(MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->getSortKey('Hello');
    }

    public function testGetStrength()
    {
        self::expectException(MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->getStrength();
    }

    public function testSetAttribute()
    {
        self::expectException(MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
    }

    public function testSetStrength()
    {
        self::expectException(MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->setStrength(Collator::PRIMARY);
    }

    public function testStaticCreate()
    {
        $collator = $this->getCollator('en');
        $collator = $collator::create('en');
        self::assertInstanceOf(Collator::class, $collator);
    }

    protected function getCollator(?string $locale): Collator
    {
        return new class($locale) extends Collator {
        };
    }
}
