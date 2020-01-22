<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

/**
 * DefaultPriorityInterface.
 *
 * @author Artem Henvald <genvaldartem@gmail.com>
 */
interface DefaultPriorityInterface
{
    /**
     * Returns the default priority for the listener.
     */
    public static function getDefaultPriority(): int;
}
