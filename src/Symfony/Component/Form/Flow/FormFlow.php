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

use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\Exception\AlreadySubmittedException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;

/**
 * FormFlow represents a multistep form.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @implements \IteratorAggregate<string, FormInterface>
 */
class FormFlow extends Form implements FormFlowInterface
{
    private ?ButtonFlowInterface $clickedFlowButton = null;
    private bool $finished = false;

    public function __construct(
        private readonly FormFlowConfigInterface $config,
        private FormFlowCursor $cursor,
    ) {
        parent::__construct($config);
    }

    public function submit(mixed $submittedData, bool $clearMissing = true): static
    {
        if ($this->isSubmitted()) {
            throw new AlreadySubmittedException('A form can only be submitted once.');
        }

        if (!\is_array($submittedData)) {
            throw new TransformationFailedException('The submitted data must be an array.');
        }

        if (!$this->isCurrentStepSubmitted($submittedData)) {
            // the submitted data doesn't match the current step,
            // it's probably a reload of a POST visit from a different step
            return $this;
        }

        $this->setClickedFlowButton($submittedData, $this);

        parent::submit($submittedData, $clearMissing);

        if (!$this->clickedFlowButton || !$this->isSubmitted() || !$this->isValid()) {
            return $this;
        }

        $this->finished = $this->clickedFlowButton->isFinishAction();

        if ($this->finished && $this->config->isAutoReset()) {
            $this->reset();
        }

        return $this;
    }

    public function reset(): void
    {
        $this->config->getDataStorage()->clear();
        $this->cursor = $this->cursor->withCurrentStep($this->config->getInitialStep());
    }

    public function movePrevious(?string $step = null): void
    {
        if ($step) {
            $this->moveBackTo($step);

            return;
        }

        if (!$this->move(static fn (FormFlowCursor $cursor) => $cursor->getPreviousStep())) {
            throw new RuntimeException('Cannot determine previous step.');
        }
    }

    public function moveNext(): void
    {
        if (!$this->move(static fn (FormFlowCursor $cursor) => $cursor->getNextStep())) {
            throw new RuntimeException('Cannot determine next step.');
        }
    }

    public function newStepForm(): static
    {
        return $this->config->getFormFactory()->createNamed($this->config->getName(), $this->config->getType()->getInnerType()::class, $this->getData(), $this->config->getInitialOptions());
    }

    public function getStepForm(): static
    {
        if (!$this->isSubmitted() || !$this->isValid()) {
            return $this;
        }

        if ($this->clickedFlowButton && !$this->clickedFlowButton->isHandled()) {
            $this->clickedFlowButton->handle();
        }

        if (!$this->isValid()) {
            return $this;
        }

        return $this->newStepForm();
    }

    public function getCursor(): FormFlowCursor
    {
        return $this->cursor;
    }

    public function getConfig(): FormFlowConfigInterface
    {
        return $this->config;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function getClickedButton(): ButtonFlowInterface|FormInterface|ClickableInterface|null
    {
        return parent::getClickedButton() ?? $this->clickedFlowButton;
    }

    private function setClickedFlowButton(mixed $submittedData, FormInterface $form): void
    {
        if (!\is_array($submittedData)) {
            return;
        }

        foreach ($form as $name => $child) {
            if (!\array_key_exists($name, $submittedData)) {
                continue;
            }

            if ($child->count() > 0) {
                $this->setClickedFlowButton($submittedData[$name], $child);

                if ($this->clickedFlowButton) {
                    return;
                }

                continue;
            }

            if (!$child instanceof ButtonFlowInterface) {
                continue;
            }

            $child->submit($submittedData[$name]);

            if ($child->isClicked()) {
                $this->clickedFlowButton = $child;
                break;
            }
        }
    }

    private function moveBackTo(string $step): void
    {
        $steps = $this->cursor->getSteps();

        if (false === $targetIndex = array_search($step, $steps)) {
            throw new InvalidArgumentException(\sprintf('Step "%s" does not exist.', $step));
        }

        $currentStep = $this->cursor->getCurrentStep();
        $currentIndex = $this->cursor->getStepIndex();

        if ($targetIndex === $currentIndex) {
            return;
        }

        if ($targetIndex > $currentIndex) {
            throw new RuntimeException(\sprintf('Cannot move back to step "%s" because it is ahead of the current step "%s".', $step, $currentStep));
        }

        while ($targetIndex < $currentIndex) {
            $this->movePrevious();
            $currentIndex = $this->cursor->getStepIndex();
        }

        if ($targetIndex > $currentIndex) {
            throw new RuntimeException(\sprintf('Cannot move back to step "%s" because it is a skipped step.', $step));
        }
    }

    private function move(\Closure $direction): bool
    {
        $data = $this->getData();
        $cursor = $this->cursor;

        while (true) {
            if (null === $newStep = $direction($cursor)) {
                return false;
            }

            if ($cursor->getCurrentStep() === $newStep) {
                return true;
            }

            $cursor = $cursor->withCurrentStep($newStep);

            if (!$this->config->getStep($newStep)->isSkipped($data)) {
                break;
            }

            if ($cursor->isLastStep()) {
                $this->finished = true;

                if ($this->config->isAutoReset()) {
                    $this->reset();

                    return true;
                }

                break;
            }
        }

        $this->cursor = $cursor;
        $this->config->getStepAccessor()->setStep($data, $newStep);
        $this->config->getDataStorage()->save($data);

        return true;
    }

    private function isCurrentStepSubmitted(array $submittedData): bool
    {
        foreach ($this->cursor->getSteps() as $step) {
            if (\array_key_exists($step, $submittedData)) {
                return $step === $this->cursor->getCurrentStep();
            }
        }

        return true;
    }
}
