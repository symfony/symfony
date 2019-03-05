<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog;

use Monolog\Logger as BaseLogger;
use Monolog\ResettableInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Logger extends BaseLogger implements DebugLoggerInterface, ResetInterface
{
    /**
     * {@inheritdoc}
     *
     * @param Request|null $request
     */
    public function getLogs(/* Request $request = null */)
    {
        if (\func_num_args() < 1 && __CLASS__ !== \get_class($this) && __CLASS__ !== (new \ReflectionMethod($this, __FUNCTION__))->getDeclaringClass()->getName() && !$this instanceof \PHPUnit\Framework\MockObject\MockObject && !$this instanceof \Prophecy\Prophecy\ProphecySubjectInterface) {
            @trigger_error(sprintf('The "%s()" method will have a new "Request $request = null" argument in version 5.0, not defining it is deprecated since Symfony 4.2.', __METHOD__), E_USER_DEPRECATED);
        }

        if ($logger = $this->getDebugLogger()) {
            return $logger->getLogs(...\func_get_args());
        }

        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @param Request|null $request
     */
    public function countErrors(/* Request $request = null */)
    {
        if (\func_num_args() < 1 && __CLASS__ !== \get_class($this) && __CLASS__ !== (new \ReflectionMethod($this, __FUNCTION__))->getDeclaringClass()->getName() && !$this instanceof \PHPUnit\Framework\MockObject\MockObject && !$this instanceof \Prophecy\Prophecy\ProphecySubjectInterface) {
            @trigger_error(sprintf('The "%s()" method will have a new "Request $request = null" argument in version 5.0, not defining it is deprecated since Symfony 4.2.', __METHOD__), E_USER_DEPRECATED);
        }

        if ($logger = $this->getDebugLogger()) {
            return $logger->countErrors(...\func_get_args());
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        if ($logger = $this->getDebugLogger()) {
            $logger->clear();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->clear();

        if ($this instanceof ResettableInterface) {
            parent::reset();
        }
    }

    /**
     * Returns a DebugLoggerInterface instance if one is registered with this logger.
     *
     * @return DebugLoggerInterface|null A DebugLoggerInterface instance or null if none is registered
     */
    private function getDebugLogger()
    {
        foreach ($this->processors as $processor) {
            if ($processor instanceof DebugLoggerInterface) {
                return $processor;
            }
        }

        foreach ($this->handlers as $handler) {
            if ($handler instanceof DebugLoggerInterface) {
                return $handler;
            }
        }
    }
}
