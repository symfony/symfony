<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow;

use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Flow\DataStorage\DataStorageInterface;
use Symfony\Component\Form\Flow\StepAccessor\StepAccessorInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A builder for creating {@link FormFlow} instances.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @implements \IteratorAggregate<string, FormBuilderInterface>
 */
class FormFlowBuilder extends FormBuilder implements FormFlowBuilderInterface
{
    /**
     * @var array<string, StepFlowBuilderConfigInterface>
     */
    private array $steps = [];
    private array $initialOptions = [];
    private DataStorageInterface $dataStorage;
    private StepAccessorInterface $stepAccessor;

    public function createStep(string $name, string $type = FormType::class, array $options = []): StepFlowBuilderConfigInterface
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }

        return new StepFlowBuilder($name, $type, $options);
    }

    public function addStep(StepFlowBuilderConfigInterface|string $name, string $type = FormType::class, array $options = [], ?callable $skip = null, int $priority = 0): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }

        if ($name instanceof StepFlowBuilderConfigInterface) {
            $this->steps[$name->getName()] = $name;

            return $this;
        }

        $this->steps[$name] = $this->createStep($name, $type, $options)
            ->setSkip($skip ? $skip(...) : null)
            ->setPriority($priority)
        ;

        return $this;
    }

    public function removeStep(string $name): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }

        unset($this->steps[$name]);

        return $this;
    }

    public function hasStep(string $name): bool
    {
        return isset($this->steps[$name]);
    }

    public function getStep(string $name): StepFlowBuilderConfigInterface
    {
        return $this->steps[$name] ?? throw new InvalidArgumentException(\sprintf('Step "%s" does not exist.', $name));
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function setInitialOptions(array $options): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }

        $this->initialOptions = $options;

        return $this;
    }

    public function getInitialStep(): string
    {
        $defaultStep = (string) key($this->steps);

        if (!isset($this->initialOptions['data'])) {
            return $defaultStep;
        }

        return (string) $this->stepAccessor->getStep($this->initialOptions['data'], $defaultStep);
    }

    public function getInitialOptions(): array
    {
        return $this->initialOptions;
    }

    public function setDataStorage(DataStorageInterface $dataStorage): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }

        $this->dataStorage = $dataStorage;

        // make sure the current data is available immediately
        $this->setData($dataStorage->load($this->getData()));

        return $this;
    }

    public function getDataStorage(): DataStorageInterface
    {
        return $this->dataStorage;
    }

    public function setStepAccessor(StepAccessorInterface $stepAccessor): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }

        $this->stepAccessor = $stepAccessor;

        return $this;
    }

    public function getStepAccessor(): StepAccessorInterface
    {
        return $this->stepAccessor;
    }

    public function isAutoReset(): bool
    {
        return $this->getOption('auto_reset');
    }

    public function getFormConfig(): FormFlowConfigInterface
    {
        /** @var self $config */
        $config = parent::getFormConfig();

        foreach ($config->steps as $name => $step) {
            $config->steps[$name] = $step->getStepConfig();
        }

        return $config;
    }

    public function getForm(): FormFlowInterface
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }

        $flow = $this->createFormFlow();

        foreach ($this->all() as $child) {
            if ($child instanceof FormFlowBuilderInterface) {
                throw new LogicException('Nested form flows is not currently supported.');
            }

            // Automatic initialization is only supported on root forms
            $flow->add($child->setAutoInitialize(false)->getForm());
        }

        if ($this->getAutoInitialize()) {
            // Automatically initialize the form if it is configured so
            $flow->initialize();
        }

        return $flow;
    }

    private function createFormFlow(): FormFlowInterface
    {
        if (!$this->steps) {
            throw new InvalidArgumentException('Steps not configured.');
        }

        uasort($this->steps, static function (StepFlowBuilderConfigInterface $a, StepFlowBuilderConfigInterface $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        $currentStep = $this->resolveCurrentStep();

        if (!isset($this->steps[$currentStep])) {
            throw new InvalidArgumentException(\sprintf('Step form "%s" is not defined.', $currentStep));
        }

        $step = $this->steps[$currentStep];
        $this->add($step->getName(), $step->getType(), $step->getOptions());

        $cursor = new FormFlowCursor(array_keys($this->steps), $currentStep);
        $this->pruneActionButtons($this, $cursor);

        return new FormFlow($this->getFormConfig(), $cursor);
    }

    private function resolveCurrentStep(): string
    {
        $data = $this->getData();

        if (!$currentStep = $this->getStepAccessor()->getStep($data)) {
            $currentStep = key($this->steps);
            $this->getStepAccessor()->setStep($data, $currentStep);
            $this->setData($data);
        }

        return $currentStep;
    }

    private function pruneActionButtons(FormBuilderInterface $builder, FormFlowCursor $cursor): void
    {
        foreach ($builder->all() as $child) {
            if ($child->count() > 0) {
                $this->pruneActionButtons($child, $cursor);

                continue;
            }

            if (!$child instanceof ButtonFlowBuilder || !\is_callable($include = $child->getOption('include_if'))) {
                continue;
            }

            if (!$include($cursor)) {
                $builder->remove($child->getName());
            }
        }
    }
}
