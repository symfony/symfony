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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Output
{
    private const SUCCESS = 'success';
    private const ERROR = 'error';
    private const TERMINATED = 'terminated';

    private $exitCode;
    private $output;
    private $task;
    private $type;

    public function __construct(TaskInterface $task, int $exitCode, ?string $output = 'undefined', string $type = self::SUCCESS)
    {
        $this->task = $task;
        $this->exitCode = $exitCode;
        $this->output = $output;
        $this->type = $type;
    }

    public static function forCli(TaskInterface $task, int $statusCode, ?string $output = 'undefined'): self
    {
        switch ($statusCode) {
            case 1:
            case 2:
            case 126:
            case 127:
                return self::forError($task, $statusCode, $output);
            case 130:
                return self::forScriptTerminated($task, $statusCode, $output);
            default:
                return self::forSuccess($task, $statusCode, $output);
        }
    }

    public static function forSuccess(TaskInterface $task, int $statusCode, ?string $output = 'undefined', string $type = self::SUCCESS): self
    {
        return new self($task, $statusCode, $output, $type);
    }

    public static function forError(TaskInterface $task, int $statusCode, ?string $output = 'undefined', string $type = self::ERROR): self
    {
        return new self($task, $statusCode, $output, $type);
    }

    public static function forScriptTerminated(TaskInterface $task, int $statusCode, ?string $output = 'undefined', string $type = self::TERMINATED): self
    {
        return new self($task, $statusCode, $output, $type);
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
