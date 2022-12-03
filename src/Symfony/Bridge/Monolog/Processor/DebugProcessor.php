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
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

class DebugProcessor implements DebugLoggerInterface, ResetInterface
{
    use CompatibilityProcessor;

    private array $records = [];
    private array $errorCount = [];
    private ?RequestStack $requestStack;

    public function __construct(RequestStack $requestStack = null)
    {
        $this->requestStack = $requestStack;
    }

    private function doInvoke(array|LogRecord $record): array|LogRecord
    {
        $key = $this->requestStack && ($request = $this->requestStack->getCurrentRequest()) ? spl_object_id($request) : '';

        $timestamp = $timestampRfc3339 = false;
        if ($record['datetime'] instanceof \DateTimeInterface) {
            $timestamp = $record['datetime']->getTimestamp();
            $timestampRfc3339 = $record['datetime']->format(\DateTimeInterface::RFC3339_EXTENDED);
        } elseif (false !== $timestamp = strtotime($record['datetime'])) {
            $timestampRfc3339 = (new \DateTimeImmutable($record['datetime']))->format(\DateTimeInterface::RFC3339_EXTENDED);
        }

        $this->records[$key][] = [
            'timestamp' => $timestamp,
            'timestamp_rfc3339' => $timestampRfc3339,
            'message' => $record['message'],
            'priority' => $record['level'],
            'priorityName' => $record['level_name'],
            'context' => $record['context'],
            'channel' => $record['channel'] ?? '',
        ];

        if (!isset($this->errorCount[$key])) {
            $this->errorCount[$key] = 0;
        }

        switch ($record['level']) {
            case Logger::ERROR:
            case Logger::CRITICAL:
            case Logger::ALERT:
            case Logger::EMERGENCY:
                ++$this->errorCount[$key];
        }

        return $record;
    }

    public function getLogs(Request $request = null): array
    {
        if (null !== $request) {
            return $this->records[spl_object_id($request)] ?? [];
        }

        if (0 === \count($this->records)) {
            return [];
        }

        return array_merge(...array_values($this->records));
    }

    public function countErrors(Request $request = null): int
    {
        if (null !== $request) {
            return $this->errorCount[spl_object_id($request)] ?? 0;
        }

        return array_sum($this->errorCount);
    }

    public function clear()
    {
        $this->records = [];
        $this->errorCount = [];
    }

    public function reset()
    {
        $this->clear();
    }
}
