<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;

trait ChoiceListAssertionTrait
{
    private function assertEqualsArrayChoiceList(ArrayChoiceList $expected, $actual)
    {
        $this->assertInstanceOf(ArrayChoiceList::class, $actual);
        $this->assertEquals($expected->getChoices(), $actual->getChoices());
        $this->assertEquals($expected->getStructuredValues(), $actual->getStructuredValues());
        $this->assertEquals($expected->getOriginalKeys(), $actual->getOriginalKeys());
    }

    private function assertEqualsLazyChoiceList(LazyChoiceList $expected, $actual)
    {
        $this->assertInstanceOf(LazyChoiceList::class, $actual);
        $this->assertEquals($expected->getChoices(), $actual->getChoices());
        $this->assertEquals($expected->getValues(), $actual->getValues());
        $this->assertEquals($expected->getOriginalKeys(), $actual->getOriginalKeys());
    }
}
