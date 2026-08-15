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
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormTypeInterface;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class StepFlowBuilder implements StepFlowBuilderConfigInterface
{
    private bool $locked = false;
    private int $priority = 0;
    private ?\Closure $skip = null;
    /** @var array<string, StepFlowBuilderConfigInterface> */
    private array $children = [];
    private bool $group = false;

    /**
     * @param class-string<FormTypeInterface> $type
     */
    public function __construct(
        private readonly string $name,
        private readonly string $type = FormType::class,
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

    public function setGroup(bool $group): StepFlowBuilderConfigInterface
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }

        $this->group = $group;

        return $this;
    }

    public function isGroup(): bool
    {
        return $this->group;
    }

    public function addStep(StepFlowBuilderConfigInterface|string $name, string $type = FormType::class, array $options = [], ?callable $skip = null, int $priority = 0): StepFlowBuilderConfigInterface
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }

        if ($name instanceof StepFlowBuilderConfigInterface) {
            $this->children[$name->getName()] = $name;

            return $this;
        }

        $this->children[$name] = new self($name, $type, $options)
            ->setSkip($skip ? $skip(...) : null)
            ->setPriority($priority);

        return $this;
    }

    public function removeStep(string $name): StepFlowBuilderConfigInterface
    {
        unset($this->children[$name]);

        return $this;
    }

    public function getSteps(): array
    {
        return $this->children;
    }

    public function hasStep(string $name): bool
    {
        return isset($this->children[$name]) || array_any($this->children, static fn (StepFlowBuilderConfigInterface $step) => $step->hasStep($name));
    }

    public function getStep(string $name): StepFlowConfigInterface
    {
        if (isset($this->children[$name])) {
            return $this->children[$name];
        }

        foreach ($this->children as $step) {
            try {
                return $step->getStep($name);
            } catch (InvalidArgumentException) {
                // Continue searching
            }
        }

        throw new InvalidArgumentException(\sprintf('Sub step "%s" does not exist in "%s" step.', $name, $this->name));
    }

    public function getStepConfig(): StepFlowConfigInterface
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }

        // This method should be idempotent, so clone the builder
        $config = clone $this;
        $config->locked = true;

        uasort($config->children, static fn (StepFlowBuilderConfigInterface $a, StepFlowBuilderConfigInterface $b) => $b->getPriority() <=> $a->getPriority());

        foreach ($config->children as $name => $step) {
            $config->children[$name] = $step->getStepConfig();
        }

        return $config;
    }
}
