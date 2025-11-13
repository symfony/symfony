<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow\Type;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\ButtonFlowInterface;
use Symfony\Component\Form\Flow\DataStorage\DataStorageInterface;
use Symfony\Component\Form\Flow\DataStorage\NullDataStorage;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\FormFlowInterface;
use Symfony\Component\Form\Flow\StepAccessor\PropertyPathStepAccessor;
use Symfony\Component\Form\Flow\StepAccessor\StepAccessorInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * A multistep form.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class FormFlowType extends AbstractFlowType
{
    public function __construct(
        private ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
        $this->propertyAccessor ??= PropertyAccess::createPropertyAccessor();
    }

    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $builder->setDataStorage($options['data_storage'] ?? new NullDataStorage());
        $builder->setStepAccessor($options['step_accessor']);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, $this->onPreSubmit(...), -100);
    }

    public function buildViewFlow(FormView $view, FormFlowInterface $form, array $options): void
    {
        $view->vars['cursor'] = $cursor = $form->getCursor();

        $index = 0;
        $position = 1;
        foreach ($form->getConfig()->getSteps() as $name => $step) {
            $isSkipped = $step->isSkipped($form->getViewData());

            $stepVars = [
                'name' => $name,
                'index' => $index++,
                'position' => $isSkipped ? -1 : $position++,
                'is_current_step' => $name === $cursor->getCurrentStep(),
                'can_be_skipped' => null !== $step->getSkip(),
                'is_skipped' => $isSkipped,
            ];

            $view->vars['steps'][$name] = $stepVars;

            if (!$isSkipped) {
                $view->vars['visible_steps'][$name] = $stepVars;
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('data_storage')
            ->default(null)
            ->allowedTypes('null', DataStorageInterface::class);

        $resolver->define('step_accessor')
            ->default(function (Options $options) {
                if (!isset($options['step_property_path'])) {
                    throw new MissingOptionsException('Option "step_property_path" is required.');
                }

                return new PropertyPathStepAccessor($this->propertyAccessor, $options['step_property_path']);
            })
            ->allowedTypes(StepAccessorInterface::class);

        $resolver->define('step_property_path')
            ->info('Required if the default step_accessor is being used')
            ->allowedTypes('string', PropertyPathInterface::class)
            ->normalize(function (Options $options, string|PropertyPathInterface $value): PropertyPathInterface {
                return \is_string($value) ? new PropertyPath($value) : $value;
            });

        $resolver->define('auto_reset')
            ->info('Whether the FormFlow will be reset automatically when it is finished')
            ->default(true)
            ->allowedTypes('bool');

        $resolver->setDefault('validation_groups', function (FormFlowInterface $flow) {
            return ['Default', $flow->getCursor()->getCurrentStep()];
        });
    }

    public function getParent(): string
    {
        return FormType::class;
    }

    public function onPreSubmit(FormEvent $event): void
    {
        /** @var FormFlowInterface $flow */
        $flow = $event->getForm();
        $button = $flow->getClickedButton();

        if ($button instanceof ButtonFlowInterface && $button->isClearSubmission()) {
            $event->setData([]);
        }
    }
}
