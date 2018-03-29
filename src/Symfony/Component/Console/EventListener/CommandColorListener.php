<?php

namespace Symfony\Component\Console\EventListener;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds support for an environment variable to enable/disable colors
 *
 * Example use case:
 *     Running commands from crontab, rather than adding '--no-asci' to all
 *     entries, adds an environment variable
 */
class CommandColorListener implements EventSubscriberInterface
{
    /**
     * @var string
     */
    protected $varName = null;

    /**
     * @param string $varName
     */
    public function __construct(string $varName)
    {
        $this->varName = $varName;
    }

    /**
     * Sets the decorated output option when an environment variable is set
     *
     * @param ConsoleCommandEvent $event
     */
    public function onConsoleCommand(ConsoleCommandEvent $event)
    {
        $color = strtolower(getenv($this->varName, true) ?: getenv($this->varName));

        if (empty($color)) {
            return;
        }

        $event->getOutput()->setDecorated(in_array($color, ['true', 'y', 'yes']));
    }

    public static function getSubscribedEvents()
    {
        return [
            ConsoleEvents::COMMAND => array('onConsoleCommand', 0),
        ];
    }
}
