<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Output;

/**
 * ClearInterface is an interface implemented by ConsoleOutput class.
 * This allows the console to be cleared.
 *
 * @author Alex Bowers <bowersbros@gmail.com>
 */
interface ClearInterface
{
    /**
     * Clears the screen of output.
     */
    public function clearScreen();
}
