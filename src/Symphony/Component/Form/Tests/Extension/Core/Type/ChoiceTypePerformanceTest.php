<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Core\Type;

use Symphony\Component\Form\Test\FormPerformanceTestCase;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceTypePerformanceTest extends FormPerformanceTestCase
{
    /**
     * This test case is realistic in collection forms where each
     * row contains the same choice field.
     *
     * @group benchmark
     */
    public function testSameChoiceFieldCreatedMultipleTimes()
    {
        $this->setMaxRunningTime(1);
        $choices = range(1, 300);

        for ($i = 0; $i < 100; ++$i) {
            $this->factory->create('Symphony\Component\Form\Extension\Core\Type\ChoiceType', mt_rand(1, 400), array(
                'choices' => $choices,
            ));
        }
    }
}
