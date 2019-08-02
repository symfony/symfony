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
use Symfony\Component\Intl\Globals\IntlGlobals;

class CollatorTest extends AbstractCollatorTest
{
    public function testConstructorWithUnsupportedLocale()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException');
        new Collator('pt_BR');
    }

    public function testCompare()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodNotImplementedException');
        $collator = $this->getCollator('en');
        $collator->compare('a', 'b');
    }

    public function testGetAttribute()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodNotImplementedException');
        $collator = $this->getCollator('en');
        $collator->getAttribute(Collator::NUMERIC_COLLATION);
    }

    public function testGetErrorCode()
    {
        $collator = $this->getCollator('en');
        $this->assertEquals(IntlGlobals::U_ZERO_ERROR, $collator->getErrorCode());
    }

    public function testGetErrorMessage()
    {
        $collator = $this->getCollator('en');
        $this->assertEquals('U_ZERO_ERROR', $collator->getErrorMessage());
    }

    public function testGetLocale()
    {
        $collator = $this->getCollator('en');
        $this->assertEquals('en', $collator->getLocale());
    }

    public function testConstructWithoutLocale()
    {
        $collator = $this->getCollator(null);
        $this->assertInstanceOf('\Symfony\Component\Intl\Collator\Collator', $collator);
    }

    public function testGetSortKey()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodNotImplementedException');
        $collator = $this->getCollator('en');
        $collator->getSortKey('Hello');
    }

    public function testGetStrength()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodNotImplementedException');
        $collator = $this->getCollator('en');
        $collator->getStrength();
    }

    public function testSetAttribute()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodNotImplementedException');
        $collator = $this->getCollator('en');
        $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
    }

    public function testSetStrength()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodNotImplementedException');
        $collator = $this->getCollator('en');
        $collator->setStrength(Collator::PRIMARY);
    }

    public function testStaticCreate()
    {
        $collator = Collator::create('en');
        $this->assertInstanceOf('\Symfony\Component\Intl\Collator\Collator', $collator);
    }

    protected function getCollator($locale)
    {
        return new Collator($locale);
    }
}
