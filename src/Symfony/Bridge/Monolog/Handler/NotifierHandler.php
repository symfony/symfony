<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\NotifierInterface;

/**
 * Uses Notifier as a log handler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since Symfony 6.1
 */
class NotifierHandler extends AbstractHandler
{
    use CompatibilityHandler;

    private NotifierInterface $notifier;

    public function __construct(NotifierInterface $notifier, string|int|Level $level = Logger::ERROR, bool $bubble = true)
    {
        $this->notifier = $notifier;

        parent::__construct(Logger::toMonologLevel($level) < Logger::ERROR ? Logger::ERROR : $level, $bubble);
    }

    private function doHandle(array|LogRecord $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        $this->notify([$record]);

        return !$this->bubble;
    }

    public function handleBatch(array $records): void
    {
        if ($records = array_filter($records, $this->isHandling(...))) {
            $this->notify($records);
        }
    }

    private function notify(array $records): void
    {
        $record = $this->getHighestRecord($records);
        if (($record['context']['exception'] ?? null) instanceof \Throwable) {
            $notification = Notification::fromThrowable($record['context']['exception']);
        } else {
            $notification = new Notification($record['message']);
        }

        $notification->importanceFromLogLevelName(Logger::getLevelName($record['level']));

        $this->notifier->send($notification, ...$this->notifier->getAdminRecipients());
    }

    private function getHighestRecord(array $records)
    {
        $highestRecord = null;
        foreach ($records as $record) {
            if (null === $highestRecord || $highestRecord['level'] < $record['level']) {
                $highestRecord = $record;
            }
        }

        return $highestRecord;
    }
}
