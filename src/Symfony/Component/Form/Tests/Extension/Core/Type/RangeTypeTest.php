<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\Test\TypeTestCase as TestCase;

/**
 * @author Pawel Krynicki <pawel.krynicki@hotmail.com>
 */
class RangeTypeTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfStepOptionNotPositive()
    {
        $this->factory->create('range', null, array('min' => 0, 'max' => 100, 'step' => -5));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testThrowExceptionIfMaxOptionsMissing()
    {
        $this->factory->create('range', null, array('min' => 0));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testThrowExceptionIfMinOptionsMissing()
    {
        $this->factory->create('range', null, array('max' => 0));
    }
}
