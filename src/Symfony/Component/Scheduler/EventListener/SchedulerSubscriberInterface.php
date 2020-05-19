<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SchedulerSubscriberInterface extends EventSubscriberInterface
{
    /**
     * The method should return an array respecting the following rules:
     *
     *  - The syntax "[*]" will register the subscriber for every scheduler.
     *  - The syntax "['schedulerOne', 'schedulerTwo']" will register the subscriber for both scheduler.
     */
    public static function getSubscribedSchedulers(): array;
}
