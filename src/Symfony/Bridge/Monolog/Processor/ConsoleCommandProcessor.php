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

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adds the current console command information to the log entry.
 *
 * @author Piotr Stankowski <git@trakos.pl>
 */
class ConsoleCommandProcessor implements EventSubscriberInterface, ResetInterface
{
    private array $commandData;
    private bool $includeArguments;
    private bool $includeOptions;

    public function __construct(bool $includeArguments = true, bool $includeOptions = false)
    {
        $this->includeArguments = $includeArguments;
        $this->includeOptions = $includeOptions;
    }

    public function __invoke(array $records)
    {
        if (isset($this->commandData) && !isset($records['extra']['command'])) {
            $records['extra']['command'] = $this->commandData;
        }

        return $records;
    }

    public function reset()
    {
        unset($this->commandData);
    }

    public function addCommandData(ConsoleEvent $event)
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
