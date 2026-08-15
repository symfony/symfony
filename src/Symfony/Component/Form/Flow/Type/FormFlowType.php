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
use Symfony\Component\Form\Flow\FormFlowCursor;
use Symfony\Component\Form\Flow\FormFlowInterface;
use Symfony\Component\Form\Flow\StepAccessor\PropertyPathStepAccessor;
use Symfony\Component\Form\Flow\StepAccessor\StepAccessorInterface;
use Symfony\Component\Form\Flow\StepFlowConfigInterface;
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
        $view->vars['steps'] = $this->buildStepsVars($form->getConfig()->getSteps(), $cursor, $form->getViewData());
        $view->vars['visible_steps'] = array_filter($view->vars['steps'], static fn ($step) => !$step['is_skipped']);
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
            ->normalize(static fn (Options $options, string|PropertyPathInterface $value): PropertyPathInterface => \is_string($value) ? new PropertyPath($value) : $value);

        $resolver->define('auto_reset')
            ->info('Whether the FormFlow will be reset automatically when it is finished')
            ->default(true)
            ->allowedTypes('bool');

        $resolver->setDefault('validation_groups', static fn (FormFlowInterface $flow) => ['Default', $flow->getCursor()->getCurrentStep()]);
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

    /**
     * @param array<string, StepFlowConfigInterface> $steps
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildStepsVars(array $steps, FormFlowCursor $cursor, mixed $viewData, int $level = 0): array
    {
        $tree = [];
        $index = 0;
        $position = 1;
        $currentStep = $cursor->getCurrentStep();

        foreach ($steps as $name => $step) {
            $children = [];
            if ($childSteps = $step->getSteps()) {
                $children = $this->buildStepsVars($childSteps, $cursor, $viewData, $level + 1);
            }

            $isSkipped = $step->isSkipped($viewData);

            $tree[$name] = [
                'name' => $name,
                'level' => $level,
                'index' => $index++,
                'position' => $isSkipped ? -1 : $position++,
                'is_before_current_step' => $cursor->getStepIndexOf($name) < $cursor->getStepIndex(),
                'is_current_step' => $name === $currentStep,
                'has_current_step_descendant' => $this->hasCurrentStepDescendant($children),
                'is_after_current_step' => $cursor->getStepIndexOf($name) > $cursor->getStepIndex(),
                'can_be_skipped' => null !== $step->getSkip(),
                'is_skipped' => $isSkipped,
                'is_group' => $step->isGroup(),
                'children' => $children,
                'visible_children' => array_filter($children, static fn (array $child): bool => !$child['is_skipped']),
            ];
        }

        return $tree;
    }

    private function hasCurrentStepDescendant(array $children): bool
    {
        return array_any($children, static fn (array $child): bool => $child['is_current_step'] || $child['has_current_step_descendant']);
    }
}
