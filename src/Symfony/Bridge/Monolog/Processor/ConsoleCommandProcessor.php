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
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adds the current console command information to the log entry.
 *
 * @author Piotr Stankowski <git@trakos.pl>
 *
 * @final since Symfony 6.1
 */
class ConsoleCommandProcessor implements EventSubscriberInterface, ResetInterface
{
    use CompatibilityProcessor;

    private array $commandDataStack = [];
    private bool $includeArguments;
    private bool $includeOptions;

    public function __construct(bool $includeArguments = true, bool $includeOptions = false)
    {
        $this->includeArguments = $includeArguments;
        $this->includeOptions = $includeOptions;
    }

    private function doInvoke(array|LogRecord $record): array|LogRecord
    {
        if ($this->commandDataStack && !isset($record['extra']['command'])) {
            $record['extra']['command'] = $this->commandDataStack[array_key_last($this->commandDataStack)];
        }

        return $record;
    }

    /**
     * @return void
     */
    public function reset()
    {
        // the command data is set on ConsoleEvents::COMMAND and removed on ConsoleEvents::TERMINATE,
        // it must outlive any reset happening while a command is still running
    }

    /**
     * @return void
     */
    public function addCommandData(ConsoleEvent $event)
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
            // lower than ConsoleHandler::onTerminate() (-255) so that records logged
            // on ConsoleEvents::TERMINATE still carry the command information
            ConsoleEvents::TERMINATE => ['removeCommandData', -2048],
        ];
    }
}
