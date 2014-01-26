<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Event;

/**
 * Allows listeners to do things to a command after
 * values have been bound to its input fields. Runs just
 * before the "intiialize" method.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class ConsoleCommandInitializeEvent extends ConsoleEvent
{
}
