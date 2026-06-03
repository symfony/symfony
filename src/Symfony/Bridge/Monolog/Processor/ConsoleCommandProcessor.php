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

use Monolog\LogRecord;
use Monolog\ResettableInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adds the current console command information to the log entry.
 *
 * @author Piotr Stankowski <git@trakos.pl>
 */
final class ConsoleCommandProcessor implements EventSubscriberInterface, ResetInterface, ResettableInterface
{
    private array $commandData;

    public function __construct(
        private bool $includeArguments = true,
        private bool $includeOptions = false,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (isset($this->commandData) && !isset($record->extra['command'])) {
            $record->extra['command'] = $this->commandData;
        }

        return $record;
    }

    public function reset(): void
    {
        unset($this->commandData);
    }

    public function addCommandData(ConsoleEvent $event): void
    {
        $this->commandData = [
            'name' => $event->getCommand()->getName(),
        ];
        if ($this->includeArguments) {
            $this->commandData['arguments'] = $event->getInput()->getArguments();
        }
        if ($this->includeOptions) {
            $this->commandData['options'] = $event->getInput()->getOptions();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['addCommandData', 1],
        ];
    }
}
