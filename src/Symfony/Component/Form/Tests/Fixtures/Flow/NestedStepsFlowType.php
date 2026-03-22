<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures\Flow;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\DataStorage\InMemoryDataStorage;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\Type\NavigatorFlowType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NestedStepsFlowType extends AbstractFlowType
{
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $builder
            ->addStep(
                $builder->createStepGroup('stepA')
                    ->addStep('stepA1', TextType::class)
                    ->addStep('stepA2', TextType::class)
                    ->addStep('stepA3', TextType::class)
            )
            ->addStep(
                $builder->createStep('stepB', ChoiceType::class, ['choices' => ['SkipB1' => 1, 'SkipB2' => 2]])
                    ->addStep(
                        $builder->createStep('stepB1')
                            ->setSkip(static fn (array $data) => 1 == ($data['stepB'] ?? null))
                            ->addStep('stepB11', TextType::class)
                            ->addStep('stepB12', TextType::class)
                    )
                    ->addStep('stepB2', TextType::class)
            )
            ->addStep('stepC', TextType::class);

        $builder->add('navigator', NavigatorFlowType::class, ['with_reset' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'data_storage' => new InMemoryDataStorage('nested_steps_flow'),
            'step_property_path' => '[currentStep]',
        ]);
    }
}
