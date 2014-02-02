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
    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testConstructorWithUnsupportedLocale()
    {
        new Collator('pt_BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testCompare()
    {
        $collator = $this->getCollator('en');
        $collator->compare('a', 'b');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
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

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetSortKey()
    {
        $collator = $this->getCollator('en');
        $collator->getSortKey('Hello');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetStrength()
    {
        $collator = $this->getCollator('en');
        $collator->getStrength();
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetAttribute()
    {
        $collator = $this->getCollator('en');
        $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetStrength()
    {
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
