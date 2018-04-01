<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Intl\Tests\Collator;

use Symphony\Component\Intl\Collator\Collator;
use Symphony\Component\Intl\Globals\IntlGlobals;

class CollatorTest extends AbstractCollatorTest
{
    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testConstructorWithUnsupportedLocale()
    {
        new Collator('pt_BR');
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testCompare()
    {
        $collator = $this->getCollator('en');
        $collator->compare('a', 'b');
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetAttribute()
    {
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
        $this->assertInstanceOf('\Symphony\Component\Intl\Collator\Collator', $collator);
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetSortKey()
    {
        $collator = $this->getCollator('en');
        $collator->getSortKey('Hello');
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetStrength()
    {
        $collator = $this->getCollator('en');
        $collator->getStrength();
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetAttribute()
    {
        $collator = $this->getCollator('en');
        $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetStrength()
    {
        $collator = $this->getCollator('en');
        $collator->setStrength(Collator::PRIMARY);
    }

    public function testStaticCreate()
    {
        $collator = Collator::create('en');
        $this->assertInstanceOf('\Symphony\Component\Intl\Collator\Collator', $collator);
    }

    protected function getCollator($locale)
    {
        return new Collator($locale);
    }
}
