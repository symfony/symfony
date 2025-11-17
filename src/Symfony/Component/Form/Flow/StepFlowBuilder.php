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
use Symfony\Component\Form\FormTypeInterface;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class StepFlowBuilder implements StepFlowBuilderConfigInterface
{
    private bool $locked = false;
    private int $priority = 0;
    private ?\Closure $skip = null;

    /**
     * @param class-string<FormTypeInterface> $type
     */
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly array $options = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }

        return $this->type;
    }

    public function getOptions(): array
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }

        return $this->options;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }

        $this->priority = $priority;

        return $this;
    }

    public function getSkip(): ?\Closure
    {
        return $this->skip;
    }

    public function isSkipped(mixed $data): bool
    {
        if (null === $this->skip) {
            return false;
        }

        return ($this->skip)($data);
    }

    public function setSkip(?\Closure $skip): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }

        $this->skip = $skip;

        return $this;
    }

    public function getStepConfig(): StepFlowConfigInterface
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }

        // This method should be idempotent, so clone the builder
        $config = clone $this;
        $config->locked = true;

        return $config;
    }
}
