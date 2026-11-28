<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Dynamic;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Dynamic\DynamicField;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Tests\Fixtures\Dynamic\DynamicMealType;
use Symfony\Component\Form\Tests\Fixtures\Dynamic\Food;
use Symfony\Component\Form\Tests\Fixtures\Dynamic\Meal;
use Symfony\Component\Form\Tests\Fixtures\Dynamic\PizzaSize;

class DynamicFormTest extends TestCase
{
    public function testInitialForm()
    {
        $form = $this->createForm();

        // hidden field holding the dynamic fields errors
        $this->assertTrue($form->has('_dynamic_error'));
        // Breakfast is the preselected meal
        $this->assertSame('What is for Breakfast?', $this->getPlaceholder($form, 'mainFood'));
        $this->assertSame(['eggs', 'bacon', 'strawberries', 'croissant'], $this->getChoiceValues($form, 'mainFood'));
        $this->assertFalse($form->has('pizzaSize'));
    }

    public function testChangingTheDependencyChangesTheChoices()
    {
        $form = $this->createForm();
        $form->submit(['meal' => 'dinner', 'mainFood' => '']);

        $this->assertTrue($form->isValid());
        $this->assertSame('What is for Dinner?', $this->getPlaceholder($form, 'mainFood'));
        $this->assertSame(['pizza', 'pint', 'pasta'], $this->getChoiceValues($form, 'mainFood'));
        $this->assertFalse($form->has('pizzaSize'));
    }

    public function testMultiLevelDependencyAddsField()
    {
        $form = $this->createForm();
        $form->submit(['meal' => 'dinner', 'mainFood' => 'pizza']);

        $this->assertTrue($form->isValid());
        $this->assertSame(Food::Pizza, $form->get('mainFood')->getData());
        $this->assertSame('What size pizza?', $this->getPlaceholder($form, 'pizzaSize'));
    }

    public function testMultiLevelDependencyIsSubmitted()
    {
        $form = $this->createForm();
        $form->submit(['meal' => 'dinner', 'mainFood' => 'pizza', 'pizzaSize' => '14']);

        $this->assertTrue($form->isValid());
        $this->assertSame(PizzaSize::Medium, $form->get('pizzaSize')->getData());
    }

    public function testFieldDependingOnSeveralFields()
    {
        $form = $this->createForm();
        $form->submit(['meal' => 'dinner', 'mainFood' => 'pizza', 'upperCasePizzaSizes' => '1']);

        $this->assertTrue($form->isValid());
        $this->assertSame('WHAT SIZE PIZZA?', $this->getPlaceholder($form, 'pizzaSize'));
    }

    public function testFieldIsRemovedWhenDependencyChanges()
    {
        $form = $this->createForm();
        $form->submit(['meal' => 'dinner', 'mainFood' => 'pizza', 'pizzaSize' => '14']);

        $this->assertTrue($form->has('pizzaSize'));

        $form = $this->createForm();
        $form->submit(['meal' => 'breakfast', 'mainFood' => 'bacon']);

        $this->assertTrue($form->isValid());
        $this->assertFalse($form->has('pizzaSize'));
    }

    public function testSubmittingAnInvalidValueForTheNewChoicesMakesTheFormInvalid()
    {
        // the meal is changed to Breakfast while the main food still is the Dinner's Pizza
        $form = $this->createForm();
        $form->submit(['meal' => 'breakfast', 'mainFood' => 'pizza', 'pizzaSize' => '14']);

        $this->assertFalse($form->isValid());
        $this->assertSame(['eggs', 'bacon', 'strawberries', 'croissant'], $this->getChoiceValues($form, 'mainFood'));
        $this->assertFalse($form->has('pizzaSize'));
        // no error is rendered on the dynamic field, but on the hidden one
        $this->assertCount(0, $form->get('mainFood')->getErrors());
        $this->assertCount(1, $form->get('_dynamic_error')->getErrors());
    }

    public function testFormIsValidAgainWithAValidValue()
    {
        $form = $this->createForm();
        $form->submit(['meal' => 'breakfast', 'mainFood' => 'bacon']);

        $this->assertTrue($form->isValid());
        $this->assertSame(Food::Bacon, $form->get('mainFood')->getData());
    }

    public function testSubmittingAnInvalidValueForTheLunch()
    {
        $form = $this->createForm();
        $form->submit(['meal' => 'lunch', 'mainFood' => 'bacon']);

        $this->assertFalse($form->isValid());
    }

    public function testFieldIsRemovedWhenTheCallbackDoesNotAddIt()
    {
        $form = Forms::createFormFactory()->createBuilder()
            ->add('type', TextType::class)
            ->addDynamic('details', 'type', static function (DynamicField $field, ?string $type) {
                if ('other' === $type) {
                    $field->add(TextType::class);
                }
            })
            ->getForm();

        $form->submit(['type' => 'other']);
        $this->assertTrue($form->has('details'));

        $form = Forms::createFormFactory()->createBuilder()
            ->add('type', TextType::class)
            ->addDynamic('details', 'type', static function (DynamicField $field, ?string $type) {
                if ('other' === $type) {
                    $field->add(TextType::class);
                }
            })
            ->getForm();

        $form->submit(['type' => 'foo']);
        $this->assertFalse($form->has('details'));
    }

    public function testDataOfAFieldRemovedDuringSubmissionIsNotExtraData()
    {
        $form = Forms::createFormFactory()->createBuilder(FormType::class, ['type' => 'other'])
            ->add('type', TextType::class)
            ->addDynamic('details', 'type', static function (DynamicField $field, ?string $type) {
                if ('other' === $type) {
                    $field->add(TextType::class);
                }
            })
            ->getForm();

        $this->assertTrue($form->has('details'));

        // the details are still submitted, but the field is removed by the new type
        $form->submit(['type' => 'foo', 'details' => 'stale']);

        $this->assertFalse($form->has('details'));
        $this->assertSame([], $form->getExtraData());
    }

    public function testDataOfAFieldNotAddedDuringSubmissionIsNotExtraData()
    {
        // the form is built without the field (e.g. new data on each request), as with Turbo
        $form = Forms::createFormFactory()->createBuilder()
            ->add('type', TextType::class)
            ->addDynamic('details', 'type', static function (DynamicField $field, ?string $type) {
                if ('other' === $type) {
                    $field->add(TextType::class);
                }
            })
            ->getForm();

        $this->assertFalse($form->has('details'));

        $form->submit(['type' => 'foo', 'details' => 'stale']);

        $this->assertFalse($form->has('details'));
        $this->assertSame([], $form->getExtraData());
    }

    public function testAddDynamicIsFluent()
    {
        $builder = Forms::createFormFactory()->createBuilder();

        $this->assertSame($builder, $builder->addDynamic('foo', 'bar', static fn () => null));
    }

    public function testAddDynamicThrowsOnceTheBuilderIsLocked()
    {
        // the config is a locked copy of the builder
        $config = Forms::createFormFactory()->createBuilder()->getFormConfig();

        $this->expectException(BadMethodCallException::class);

        $config->addDynamic('foo', 'bar', static fn () => null);
    }

    private function createForm(): FormInterface
    {
        return Forms::createFormFactory()->create(DynamicMealType::class, ['meal' => Meal::Breakfast]);
    }

    private function getPlaceholder(FormInterface $form, string $child): ?string
    {
        return $form->get($child)->getConfig()->getOption('placeholder');
    }

    /**
     * @return list<string>
     */
    private function getChoiceValues(FormInterface $form, string $child): array
    {
        return array_map(
            static fn ($choice) => $choice->value,
            $form->get($child)->createView()->vars['choices']
        );
    }
}
