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

use Symfony\Component\Form\Extension\Core\Type\BoundsType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BoundsTypeValidatorExtensionTest extends BaseValidatorExtensionTestCase
{
    protected function createForm(array $options = [])
    {
        return $this->factory->create(BoundsType::class, null, $options);
    }

    public function testErrorMapping()
    {
        $form = $this->createForm();

        $this->assertSame(['.' => 'from'], $form->getConfig()->getOption('error_mapping'));
    }

    public function testErrorMappingCanBeOverridden()
    {
        $form = $this->createForm(['error_mapping' => ['.' => 'to']]);

        $this->assertSame(['.' => 'to'], $form->getConfig()->getOption('error_mapping'));
    }

    public function testAViolationOnTheRangeIsReportedOnTheLowerBound()
    {
        $form = $this->createForm([
            'type' => IntegerType::class,
            'constraints' => [new Callback(static function (mixed $range, ExecutionContextInterface $context): void {
                if (isset($range['from'], $range['to']) && $range['from'] >= $range['to']) {
                    $context->addViolation('The range is empty.');
                }
            })],
        ]);
        $form->submit(['from' => '60', 'to' => '30']);

        $this->assertCount(0, $form->getErrors());
        $this->assertCount(0, $form['to']->getErrors());
        $this->assertCount(1, $errors = $form['from']->getErrors());
        $this->assertSame('The range is empty.', $errors[0]->getMessage());
        $this->assertSame('from', $errors[0]->getOrigin()->getName());
    }

    public function testAViolationOnTheRangeStaysOnTheLowerBoundWithACompoundInnerType()
    {
        // a compound inner type bubbles its errors by default, which would move the violation
        // back to the range, where "bounds_row" does not render it
        $form = $this->createForm([
            'type' => FormType::class,
            'constraints' => [new Callback(static function (mixed $range, ExecutionContextInterface $context): void {
                $context->addViolation('The range is empty.');
            })],
        ]);
        $form->submit(['from' => [], 'to' => []]);

        $this->assertCount(0, $form->getErrors());
        $this->assertCount(1, $errors = $form['from']->getErrors());
        $this->assertSame('The range is empty.', $errors[0]->getMessage());
    }

    public function testAValidRangeIsLeftAlone()
    {
        $form = $this->createForm([
            'type' => IntegerType::class,
            'constraints' => [new Callback(static function (mixed $range, ExecutionContextInterface $context): void {
                if (isset($range['from'], $range['to']) && $range['from'] >= $range['to']) {
                    $context->addViolation('The range is empty.');
                }
            })],
        ]);
        $form->submit(['from' => '18', 'to' => '42']);

        $this->assertCount(0, $form->getErrors(true));
    }
}
