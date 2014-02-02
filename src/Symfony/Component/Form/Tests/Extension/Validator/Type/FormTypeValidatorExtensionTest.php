<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Type;

use Symfony\Component\Validator\ConstraintViolationList;

class FormTypeValidatorExtensionTest extends BaseValidatorExtensionTest
{
    public function testSubmitValidatesData()
    {
        $builder = $this->factory->createBuilder(
            'form',
            null,
            array(
                'validation_groups' => 'group',
            )
        );
        $builder->add('firstName', 'form');
        $form = $builder->getForm();

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($this->equalTo($form))
            ->will($this->returnValue(new ConstraintViolationList()));

        // specific data is irrelevant
        $form->submit(array());
    }

    protected function createForm(array $options = array())
    {
        return $this->factory->create('form', null, $options);
    }
}
