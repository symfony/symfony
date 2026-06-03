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

use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class FormFlowCursor
{
    /**
     * @param array<string> $steps
     */
    public function __construct(
        private readonly array $steps,
        private readonly string $currentStep,
    ) {
        if (!\in_array($currentStep, $steps, true)) {
            throw new InvalidArgumentException(\sprintf('Step "%s" does not exist. Available steps are: "%s".', $currentStep, implode('", "', $steps)));
        }
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getTotalSteps(): int
    {
        return \count($this->steps);
    }

    public function getStepIndex(): int
    {
        return (int) array_search($this->currentStep, $this->steps, true);
    }

    public function getFirstStep(): string
    {
        return $this->steps[0];
    }

    public function getPreviousStep(): ?string
    {
        $currentPos = array_search($this->currentStep, $this->steps, true);

        return $this->steps[$currentPos - 1] ?? null;
    }

    public function getCurrentStep(): string
    {
        return $this->currentStep;
    }

    public function withCurrentStep(string $step): self
    {
        return new self($this->steps, $step);
    }

    public function getNextStep(): ?string
    {
        $currentPos = array_search($this->currentStep, $this->steps, true);

        return $this->steps[$currentPos + 1] ?? null;
    }

    public function getLastStep(): string
    {
        return $this->steps[\count($this->steps) - 1];
    }

    public function isFirstStep(): bool
    {
        return 0 === array_search($this->currentStep, $this->steps, true);
    }

    public function isLastStep(): bool
    {
        $currentPos = array_search($this->currentStep, $this->steps, true);

        return \count($this->steps) === $currentPos + 1;
    }

    public function canMoveBack(): bool
    {
        return null !== $this->getPreviousStep();
    }

    public function canMoveNext(): bool
    {
        return null !== $this->getNextStep();
    }
}
