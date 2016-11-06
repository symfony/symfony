<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 9/26/16
 * Time: 10:27 PM
 */

namespace Symfony\Component\Profiler\Context;

use Symfony\Component\Console\Command\Command;

class ConsoleCommandContext implements ContextInterface
{
    protected $exception;
    protected $exitCode;
    protected $command;

    /**
     * ConsoleCommandData constructor.
     * @param $exception
     * @param $exitCode
     * @param $command
     */
    public function __construct(Command $command, $exitCode, $exception = null)
    {
        if (!is_null($exception) && !$exception instanceof \Exception && !$exception instanceof \Throwable) {
            throw new \InvalidArgumentException('$exception must be either null or an exception');
        }

        $this->exception = $exception;
        $this->exitCode = $exitCode;
        $this->command = $command;
    }

    public function getException()
    {
        return $this->exception;
    }

    public function getName()
    {
        return $this->command->getName();
    }

    public function getStatusCode()
    {
        return $this->exitCode;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return Types::COMMAND;
    }
}