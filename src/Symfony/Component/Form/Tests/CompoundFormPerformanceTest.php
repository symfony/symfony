<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompoundFormPerformanceTest extends \Symfony\Component\Form\Tests\FormPerformanceTestCase
{
    /**
     * Create a compound form multiple times, as happens in a collection form
     *
     * @group benchmark
     */
    public function testArrayBasedForm()
    {
        $this->setMaxRunningTime(1);

        for ($i = 0; $i < 40; ++$i) {
            $form = $this->factory->createBuilder('form')
                ->add('firstName', 'text')
                ->add('lastName', 'text')
                ->add('gender', 'choice', array(
                    'choices' => array('male' => 'Male', 'female' => 'Female'),
                    'required' => false,
                ))
                ->add('age', 'number')
                ->add('birthDate', 'birthday')
                ->add('city', 'choice', array(
                    // simulate 300 different cities
                    'choices' => range(1, 300),
                ))
                ->getForm();

            // load the form into a view
            $form->createView();
        }
    }
}
