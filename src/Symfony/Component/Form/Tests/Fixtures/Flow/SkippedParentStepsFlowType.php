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
 * intro, p(skipped) => [p1, p2(skip => false)], g(group, skipped) => [g1], q.
 */
class SkippedParentStepsFlowType extends AbstractFlowType
{
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $builder
            ->addStep('intro', TextType::class, [], static fn (array $data) => $data['skipIntro'] ?? false)
            ->addStep(
                $builder->createStep('p', TextType::class)
                    ->setSkip(static fn (array $data) => true)
                    ->addStep('p1', TextType::class)
                    ->addStep(
                        $builder->createStep('p2', TextType::class)
                            ->setSkip(static fn (array $data) => false)
                    )
            )
            ->addStep(
                $builder->createStepGroup('g')
                    ->setSkip(static fn (array $data) => true)
                    ->addStep('g1', TextType::class)
            )
            ->addStep('q', TextType::class);

        $builder->add('navigator', NavigatorFlowType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'data_storage' => new InMemoryDataStorage('skipped_parent_steps_flow'),
            'step_property_path' => '[currentStep]',
        ]);
    }
}
