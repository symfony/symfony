<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console;

/**
 * Utility class for keyboards.

 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Keyboard
{
    const KEY_UP_ARROW = 0;
    const KEY_DOWN_ARROW = 1;
    const KEY_SPACEBAR = 2;
    const KEY_ENTER = 3;
    const KEY_BACKSPACE = 4;

    /**
     * Gets the pressed key from the input stream.
     * It supposes stty is available.
     *
     * @param resource $inputStream A readable input stream
     *
     * @return int|null A KEY_* constant or null if not found
     */
    public static function getPressedKey($inputStream)
    {
        $key = null;
        $sttyMode = shell_exec('stty -g');

        // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
        shell_exec('stty -icanon -echo');

        $c = fread($inputStream, 1);

        if ("\177" === $c) {
            $key = self::KEY_BACKSPACE;
        } elseif (' ' === $c) {
            $key = self::KEY_SPACEBAR;
        } elseif ("\n" === $c) {
            $key = self::KEY_ENTER;
        } elseif ("\033" === $c) {
            $c .= fread($inputStream, 2);

            if (isset($c[2])) {
                if ('A' === $c[2]) {
                    $key = self::KEY_UP_ARROW;
                } elseif ('B' === $c[2]) {
                    $key = self::KEY_DOWN_ARROW;
                }
            }
        }

        // Reset stty so it behaves normally again
        shell_exec(sprintf('stty %s', $sttyMode));

        return $key;
    }

    /**
     * This class cannot be instanciated.
     */
    private function __construct()
    {
    }
}
