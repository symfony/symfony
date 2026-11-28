<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures\Dynamic;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Dynamic\DynamicField;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

class DynamicMealType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('meal', EnumType::class, [
            'class' => Meal::class,
            'choice_label' => fn (Meal $meal): string => $meal->getReadable(),
            'placeholder' => 'Which meal is it?',
        ]);

        $builder->add('upperCasePizzaSizes', CheckboxType::class, [
            'mapped' => false,
        ]);

        $builder->addDynamic('mainFood', ['meal'], function (DynamicField $field, ?Meal $meal) {
            $field->add(EnumType::class, [
                'class' => Food::class,
                'placeholder' => null === $meal ? 'Select a meal first' : \sprintf('What is for %s?', $meal->getReadable()),
                'choices' => $meal?->getFoodChoices(),
                'choice_label' => fn (Food $food): string => $food->getReadable(),
                'disabled' => null === $meal,
            ]);
        });

        $builder->addDynamic('pizzaSize', ['mainFood', 'upperCasePizzaSizes'], function (DynamicField $field, ?Food $food, bool $upperCasePizzaSizes) {
            if (Food::Pizza !== $food) {
                return;
            }

            $field->add(EnumType::class, [
                'class' => PizzaSize::class,
                'placeholder' => $upperCasePizzaSizes ? strtoupper('What size pizza?') : 'What size pizza?',
                'choice_label' => fn (PizzaSize $pizzaSize): string => $upperCasePizzaSizes ? strtoupper($pizzaSize->getReadable()) : $pizzaSize->getReadable(),
                'required' => true,
            ]);
        });
    }
}
