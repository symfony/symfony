<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process;

/**
 * An executable finder specifically designed for the PHP executable.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PhpExecutableFinder
{
    private $executableFinder;

    public function __construct()
    {
        $this->executableFinder = new ExecutableFinder();
    }

    /**
     * Finds The PHP executable.
     *
     * @return string|false The PHP executable path or false if it cannot be found
     */
    public function find()
    {
        // HHVM support
        if (defined('HHVM_VERSION') && false !== $hhvm = getenv('PHP_BINARY')) {
            return $hhvm;
        }

        // PHP_BINARY return the current sapi executable
        if (defined('PHP_BINARY') && PHP_BINARY && ('cli' === PHP_SAPI) && is_file(PHP_BINARY)) {
            return PHP_BINARY;
        }

        if ($php = getenv('PHP_PATH')) {
            if (!is_executable($php)) {
                return false;
            }

            return $php;
        }

        if ($php = getenv('PHP_PEAR_PHP_BIN')) {
            if (is_executable($php)) {
                return $php;
            }
        }

        $dirs = array(PHP_BINDIR);
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $dirs[] = 'C:\xampp\php\\';
        }

        return $this->executableFinder->find('php', false, $dirs);
    }
}
