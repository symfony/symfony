<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Scheduler\Expression\ExpressionValidator;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractTask implements TaskInterface
{
    private $name;
    private $options;

    public function __construct(string $name, array $options = [], array $additionalOptions = [])
    {
        $this->name = $name;
        $this->options = $this->defineOptions($options, $additionalOptions);
        $this->setTag($name);
    }

    /**
     * @param array $options           The default $options allowed in every task
     * @param array $additionalOptions An array of key => types that define extra allowed $options (ex: ['timezone' => 'string'])
     *
     * @return array The resolved|validated metadata
     */
    private function defineOptions(array $options = [], array $additionalOptions = []): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'arguments' => [],
            'command' => null,
            'description' => null,
            'expression' => '* * * * *',
            'execution_mode' => null,
            'execution_absolute_deadline' => null,
            'execution_computation_time' => null,
            'execution_period' => null,
            'execution_relative_deadline' => null,
            'execution_start_time' => null,
            'execution_ending_date' => null,
            'isolated' => false,
            'shared' => false,
            'last_execution' => null,
            'max_duration' => null,
            'nice' => null,
            'output' => false,
            'priority' => 0,
            'queued' => false,
            'arrival_time' => null,
            'state' => TaskInterface::ENABLED,
            'timezone' => null,
            'tracked' => false,
            'tags' => [],
            'type' => null,
        ]);

        $resolver->setAllowedTypes('arguments', ['string[]', 'int[]']);
        $resolver->setAllowedTypes('arrival_time', [\DateTimeInterface::class, 'null']);
        $resolver->setAllowedTypes('command', ['string', 'null']);
        $resolver->setAllowedTypes('description', ['string', 'null']);
        $resolver->setAllowedTypes('expression', ['string', \DateTimeInterface::class]);
        $resolver->setAllowedTypes('execution_mode', ['string', 'null']);
        $resolver->setAllowedTypes('isolated', ['bool']);
        $resolver->setAllowedTypes('last_execution', [\DateTimeInterface::class, 'null']);
        $resolver->setAllowedTypes('execution_absolute_deadline', [\DateInterval::class, 'null']);
        $resolver->setAllowedTypes('execution_computation_time', [\DateTimeInterface::class, 'null']);
        $resolver->setAllowedTypes('execution_relative_deadline', ['int', 'float', 'string', \DateTimeInterface::class, 'null']);
        $resolver->setAllowedTypes('execution_start_time', ['int', 'float', \DateTimeInterface::class, 'null']);
        $resolver->setAllowedTypes('execution_ending_date', ['int', 'float', \DateTimeInterface::class, 'null']);
        $resolver->setAllowedTypes('max_duration', ['int', 'float', 'null']);
        $resolver->setAllowedTypes('nice', ['int', 'float', 'null']);
        $resolver->setAllowedTypes('output', 'bool');
        $resolver->setAllowedTypes('shared', ['bool']);
        $resolver->setAllowedTypes('priority', 'int');
        $resolver->setAllowedTypes('queued', ['bool']);
        $resolver->setAllowedTypes('state', 'string');
        $resolver->setAllowedTypes('timezone', [\DateTimeZone::class, 'null']);
        $resolver->setAllowedTypes('tracked', ['bool']);
        $resolver->setAllowedTypes('tags', ['array', 'null']);
        $resolver->setAllowedTypes('type', ['string', 'null']);
        $resolver->setAllowedValues('expression', function ($expression): bool {
            return $this->handleTimeRelativeTasks($expression);
        });
        $resolver->setAllowedValues('priority', function (int $priority): bool {
            return $priority <= 1000 && $priority >= -1000;
        });

        $resolver->setInfo('arrival_time', 'The time when the task is retrieved in order to execute it');
        $resolver->setInfo('execution_absolute_deadline', 'An addition of the "execution_start_time" and "execution_relative_deadline" options');
        $resolver->setInfo('execution_relative_deadline', 'The estimated ending date of the task execution (can be a valid \DatetimeInterface expression or a "strtotime" one)');
        $resolver->setInfo('execution_start_time', 'The start time of the task execution');
        $resolver->setInfo('execution_ending_date', 'The date where the execution is finished');
        $resolver->setInfo('execution_computation_time', 'The CPU time necessary to execute this task');

        if (0 === \count($additionalOptions)) {
            return $resolver->resolve($options);
        }

        foreach ($additionalOptions as $additionalOption => $allowedTypes) {
            $resolver->setDefined($additionalOption);
            $resolver->setAllowedTypes($additionalOption, $allowedTypes);
        }

        return $resolver->resolve($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommand(): ?string
    {
        return $this->options['command'];
    }

    /**
     * {@inheritdoc}
     */
    public function getExpression()
    {
        $expression = $this->options['expression'];

        if ($expression instanceof \DateTimeInterface) {
            return $expression;
        }

        if (!\is_bool($date = strtotime($expression))) {
            return \DateTimeImmutable::createFromFormat('U', $date);
        }

        return $expression;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->options['type'];
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        return \array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value = null): void
    {
        if ('tags' === $key) {
            foreach ((array) $value as $tag) {
                $this->options['tags'][] = $tag;
            }
        }

        if (\array_key_exists($key, $this->options) && null !== $value) {
            $this->options[$key] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiples(array $options = []): void
    {
        if (0 === \count($options)) {
            return;
        }

        foreach ($options as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFormattedInformations(): array
    {
        return [
            'name' => $this->getName(),
            'command' => $this->getCommand(),
            'description' => $this->get('description'),
            'expression' => $this->get('expression'),
            'execution_mode' => $this->get('execution_mode'),
            'last_execution' => $this->get('last_execution'),
            'output' => $this->get('output'),
            'queued' => $this->get('queued'),
            'timezone' => $this->get('timezone'),
            'priority' => $this->get('priority'),
            'state' => $this->get('state'),
            'scheduled_at' => $this->get('scheduled_at'),
            'tags' => $this->get('tags'),
            'type' => $this->get('type'),
        ];
    }

    private function setTag(string $name): void
    {
        $this->set('tags', explode('.', $name));
    }

    /**
     * @param string|\DateTimeInterface $expression
     */
    private function handleTimeRelativeTasks($expression): bool
    {
        return (strtotime($expression)) || (ExpressionValidator::validateString($expression)) || ($expression instanceof \DateTimeInterface);
    }
}
