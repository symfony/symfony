<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests;

use Symphony\Component\Form\Test\FormPerformanceTestCase;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompoundFormPerformanceTest extends FormPerformanceTestCase
{
    /**
     * Create a compound form multiple times, as happens in a collection form.
     *
     * @group benchmark
     */
    public function testArrayBasedForm()
    {
        $this->setMaxRunningTime(1);

        for ($i = 0; $i < 40; ++$i) {
            $form = $this->factory->createBuilder('Symphony\Component\Form\Extension\Core\Type\FormType')
                ->add('firstName', 'Symphony\Component\Form\Extension\Core\Type\TextType')
                ->add('lastName', 'Symphony\Component\Form\Extension\Core\Type\TextType')
                ->add('gender', 'Symphony\Component\Form\Extension\Core\Type\ChoiceType', array(
                    'choices' => array('male' => 'Male', 'female' => 'Female'),
                    'required' => false,
                ))
                ->add('age', 'Symphony\Component\Form\Extension\Core\Type\NumberType')
                ->add('birthDate', 'Symphony\Component\Form\Extension\Core\Type\BirthdayType')
                ->add('city', 'Symphony\Component\Form\Extension\Core\Type\ChoiceType', array(
                    // simulate 300 different cities
                    'choices' => range(1, 300),
                ))
                ->getForm();

            // load the form into a view
            $form->createView();
        }
    }
}
