<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Processor;

use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

class DebugProcessor implements DebugLoggerInterface, ResetInterface
{
    private $records = [];
    private $errorCount = [];
    private $requestStack;

    public function __construct(RequestStack $requestStack = null)
    {
        $this->requestStack = $requestStack;
    }

    public function __invoke(array $record)
    {
        $hash = $this->requestStack && ($request = $this->requestStack->getCurrentRequest()) ? spl_object_hash($request) : '';

        $this->records[$hash][] = [
            'timestamp' => $record['datetime'] instanceof \DateTimeInterface ? $record['datetime']->getTimestamp() : strtotime($record['datetime']),
            'message' => $record['message'],
            'priority' => $record['level'],
            'priorityName' => $record['level_name'],
            'context' => $record['context'],
            'channel' => isset($record['channel']) ? $record['channel'] : '',
        ];

        if (!isset($this->errorCount[$hash])) {
            $this->errorCount[$hash] = 0;
        }

        switch ($record['level']) {
            case Logger::ERROR:
            case Logger::CRITICAL:
            case Logger::ALERT:
            case Logger::EMERGENCY:
                ++$this->errorCount[$hash];
        }

        return $record;
    }

    /**
     * {@inheritdoc}
     *
     * @param Request|null $request
     */
    public function getLogs(/* Request $request = null */)
    {
        if (\func_num_args() < 1 && __CLASS__ !== static::class && __CLASS__ !== (new \ReflectionMethod($this, __FUNCTION__))->getDeclaringClass()->getName() && !$this instanceof \PHPUnit\Framework\MockObject\MockObject && !$this instanceof \Prophecy\Prophecy\ProphecySubjectInterface) {
            @trigger_error(sprintf('The "%s()" method will have a new "Request $request = null" argument in version 5.0, not defining it is deprecated since Symfony 4.2.', __METHOD__), E_USER_DEPRECATED);
        }

        if (1 <= \func_num_args() && null !== $request = func_get_arg(0)) {
            return $this->records[spl_object_hash($request)] ?? [];
        }

        if (0 === \count($this->records)) {
            return [];
        }

        return array_merge(...array_values($this->records));
    }

    /**
     * {@inheritdoc}
     *
     * @param Request|null $request
     */
    public function countErrors(/* Request $request = null */)
    {
        if (\func_num_args() < 1 && __CLASS__ !== static::class && __CLASS__ !== (new \ReflectionMethod($this, __FUNCTION__))->getDeclaringClass()->getName() && !$this instanceof \PHPUnit\Framework\MockObject\MockObject && !$this instanceof \Prophecy\Prophecy\ProphecySubjectInterface) {
            @trigger_error(sprintf('The "%s()" method will have a new "Request $request = null" argument in version 5.0, not defining it is deprecated since Symfony 4.2.', __METHOD__), E_USER_DEPRECATED);
        }

        if (1 <= \func_num_args() && null !== $request = func_get_arg(0)) {
            return $this->errorCount[spl_object_hash($request)] ?? 0;
        }

        return array_sum($this->errorCount);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->records = [];
        $this->errorCount = [];
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->clear();
    }
}
