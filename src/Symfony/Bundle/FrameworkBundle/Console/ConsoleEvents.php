<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Console;

/**
 * Contains all events thrown during Console commands execution
 *
 * @author Francesco Levorato <git@flevour.net>
 */
final class ConsoleEvents
{
    /**
     * The INIT event allows you to attach listeners before any command is
     * executed by the console. It also allows you to modify the input and output
     * before they are handled to the command.
     *
     * The event listener method receives a \Symfony\Bundle\FrameworkBundle\Event\ConsoleEvent
     * instance.
     *
     * @var string
     */
    const INIT = 'console.init';

    /**
     * The TERMINATE event allows you to attach listeners after a command is
     * executed by the console.
     *
     * The event listener method receives a \Symfony\Bundle\FrameworkBundle\Event\ConsoleTerminateEvent
     * instance.
     *
     * @var string
     */
    const TERMINATE = 'console.terminate';
}
