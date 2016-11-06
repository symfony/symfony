<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 9/26/16
 * Time: 10:27 PM
 */

namespace Symfony\Component\Profiler\Data;

use Symfony\Component\Console\Command\Command;

class ConsoleCommandData implements DataInterface
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

    /**
     * @return \Exception|\Throwable|null
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @return null|string
     */
    public function getUri()
    {
        return sprintf('command=>%s', $this->command->getName());
    }

    /**
     * @return null|string
     */
    public function getStatusCode()
    {
        return $this->exitCode;
    }
}