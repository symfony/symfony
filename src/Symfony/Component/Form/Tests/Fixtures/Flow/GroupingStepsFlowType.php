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

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\DataStorage\InMemoryDataStorage;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\Type\NavigatorFlowType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * a(group) => [a1, a2], b, c(group) => [c1].
 */
class GroupingStepsFlowType extends AbstractFlowType
{
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $builder
            ->addStep(
                $builder->createStepGroup('a')
                    ->addStep('a1', TextType::class)
                    ->addStep('a2', TextType::class)
            )
            ->addStep('b', TextType::class)
            ->addStep(
                $builder->createStepGroup('c')
                    ->addStep('c1', TextType::class)
            );

        $builder->add('navigator', NavigatorFlowType::class, ['with_reset' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'data_storage' => new InMemoryDataStorage('group_steps_flow'),
            'step_property_path' => '[currentStep]',
        ]);
    }
}
