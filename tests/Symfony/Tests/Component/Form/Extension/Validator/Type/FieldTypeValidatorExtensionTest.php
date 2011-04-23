<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Validator\Type;

class FieldTypeValidatorExtensionTest extends TypeTestCase
{
    public function testValidationGroupNullByDefault()
    {
        $form =  $this->factory->create('field');

        $this->assertNull($form->getAttribute('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToString()
    {
        $form = $this->factory->create('field', null, array(
            'validation_groups' => 'group',
        ));

        $this->assertEquals(array('group'), $form->getAttribute('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToArray()
    {
        $form = $this->factory->create('field', null, array(
            'validation_groups' => array('group1', 'group2'),
        ));

        $this->assertEquals(array('group1', 'group2'), $form->getAttribute('validation_groups'));
    }

    public function testBindValidatesData()
    {
        $builder = $this->factory->createBuilder('field', null, array(
            'validation_groups' => 'group',
        ));
        $builder->add('firstName', 'field');
        $form = $builder->getForm();

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($this->equalTo($form));

        // specific data is irrelevant
        $form->bind(array());
    }
}
