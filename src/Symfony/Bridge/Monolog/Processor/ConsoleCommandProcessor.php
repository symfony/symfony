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
use Monolog\Processor\ProcessorInterface;
use Monolog\ResettableInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adds the current console command information to the log entry.
 *
 * @author Piotr Stankowski <git@trakos.pl>
 */
final class ConsoleCommandProcessor implements EventSubscriberInterface, ResetInterface, ResettableInterface, ProcessorInterface
{
    private array $commandDataStack = [];

    public function __construct(
        private bool $includeArguments = true,
        private bool $includeOptions = false,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($this->commandDataStack && !isset($record->extra['command'])) {
            $record->extra['command'] = $this->commandDataStack[array_key_last($this->commandDataStack)];
        }

        return $record;
    }

    public function reset(): void
    {
        // the command data is set on ConsoleEvents::COMMAND and removed on ConsoleEvents::TERMINATE,
        // it must outlive any reset happening while a command is still running
    }

    public function addCommandData(ConsoleEvent $event): void
    {
        $commandData = [
            'name' => $event->getCommand()->getName(),
        ];
        if ($this->includeArguments) {
            $commandData['arguments'] = $event->getInput()->getArguments();
        }
        if ($this->includeOptions) {
            $commandData['options'] = $event->getInput()->getOptions();
        }

        $this->commandDataStack[] = $commandData;
    }

    public function removeCommandData(): void
    {
        array_pop($this->commandDataStack);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['addCommandData', 1],
            // records logged on ConsoleEvents::TERMINATE must still carry the command information
            ConsoleEvents::TERMINATE => ['method' => 'removeCommandData', 'priority' => -2048, 'after' => ConsoleHandler::class],
        ];
    }
}
