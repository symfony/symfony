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

use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;
use Symfony\Component\Form\Tests\Extension\Core\Type\FormTypeTest;
use Symfony\Component\Form\Tests\Extension\Core\Type\TextTypeTest;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;

class FormTypeValidatorExtensionTest extends BaseValidatorExtensionTest
{
    use ValidatorExtensionTrait;

    public function testSubmitValidatesData()
    {
        $builder = $this->factory->createBuilder(
            FormTypeTest::TESTED_TYPE,
            null,
            [
                'validation_groups' => 'group',
            ]
        );
        $builder->add('firstName', FormTypeTest::TESTED_TYPE);
        $form = $builder->getForm();

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($this->equalTo($form))
            ->willReturn(new ConstraintViolationList());

        // specific data is irrelevant
        $form->submit([]);
    }

    public function testValidConstraint()
    {
        $form = $this->createForm(['constraints' => $valid = new Valid()]);

        $this->assertSame([$valid], $form->getConfig()->getOption('constraints'));
    }

    public function testGroupSequenceWithConstraintsOption()
    {
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->create(FormTypeTest::TESTED_TYPE, null, (['validation_groups' => new GroupSequence(['First', 'Second'])]))
            ->add('field', TextTypeTest::TESTED_TYPE, [
                'constraints' => [
                    new Length(['min' => 10, 'groups' => ['First']]),
                    new Email(['groups' => ['Second']]),
                ],
            ])
        ;

        $form->submit(['field' => 'wrong']);

        $this->assertCount(1, $form->getErrors(true));
    }

    /**
     * @dataProvider provideGroupsSequenceAndResultData
     */
    public function testGroupSequenceWithConstraintsOptionMatrix(
        array $groups,
        array $sequence,
        $errorCount,
        array $propertyPaths
    ) {
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->create(FormTypeTest::TESTED_TYPE, null, ([
                'validation_groups' => new GroupSequence($sequence),
            ]));

        $data = [];
        foreach ($groups as $fieldName => $fieldGroups) {
            $form = $form->add(
                $fieldName, TextTypeTest::
                TESTED_TYPE,
                [
                    'constraints' => [new NotBlank(['groups' => $fieldGroups])],
                ]);

            $data[$fieldName] = '';
        }

        $form->submit($data);

        $errors = $form->getErrors(true);
        $this->assertCount($errorCount, $form->getErrors(true));

        foreach ($errors as $i => $error) {
            $this->assertEquals('children['.$propertyPaths[$i].'].data', $error->getCause()->getPropertyPath());
        }
    }

    public function provideGroupsSequenceAndResultData()
    {
        return [
            // two fields (sequence of groups and group order):
            [
                'groups' => [
                    'field1' => ['First'],
                    'field2' => ['Second'],
                ],
                'sequence' => ['First'],
                'errors' => 1,
                'propertyPaths' => ['field1'],
            ],
            [
                'groups' => [
                    'field1' => ['First'],
                    'field2' => ['Second'],
                ],
                'sequence' => ['Second'],
                'errors' => 1,
                'propertyPaths' => ['field2'],
            ],
            [
                'groups' => [
                    'field1' => ['First'],
                    'field2' => ['Second'],
                ],
                'sequence' => ['First', 'Second'],
                'errors' => 1,
                'propertyPaths' => ['field1'],
            ],
            [
                'groups' => [
                    'field1' => ['First'],
                    'field2' => ['Second'],
                ],
                'sequence' => ['Second', 'First'],
                'errors' => 1,
                'propertyPaths' => ['field2'],
            ],

            // two fields (field with sequence of groups)
            [
                'groups' => [
                    'field1' => ['First'],
                    'field2' => ['Second', 'First'],
                ],
                'sequence' => ['First'],
                'errors' => 2,
                'propertyPaths' => ['field1', 'field2'],
            ],

            // three fields (sequence with multigroup)
            [
                'groups' => [
                    'field1' => ['First'],
                    'field2' => ['Second'],
                    'field3' => ['Third'],
                ],
                'sequence' => [['First', 'Second'], 'Third'],
                'errors' => 2,
                'propertyPaths' => ['field1', 'field2'],
            ],
            [
                'groups' => [
                    'field1' => ['First'],
                    'field2' => ['Second'],
                    'field3' => ['Third'],
                ],
                'sequence' => ['First', ['Second', 'Third']],
                'errors' => 1,
                'propertyPaths' => ['field1'],
            ],

            // three fields (field with sequence of groups)
            [
                'groups' => [
                    'field1' => ['First'],
                    'field2' => ['Second'],
                    'field3' => ['Third', 'Second'],
                ],
                'sequence' => [['First', 'Second'], 'Third'],
                'errors' => 3,
                'propertyPaths' => ['field1', 'field2', 'field3'],
            ],
        ];
    }

    protected function createForm(array $options = [])
    {
        return $this->factory->create(FormTypeTest::TESTED_TYPE, null, $options);
    }
}
