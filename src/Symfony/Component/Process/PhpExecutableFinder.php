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

use Symfony\Component\Process\Exception\FileNotExecutableException;
use Symfony\Component\Process\Exception\ExecutableNotFoundException;

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
     * @return string The PHP executable path
     */
    public function get()
    {
        if ($php = getenv('PHP_PATH')) {
            if (!is_executable($php)) {
                throw new FileNotExecutableException($php);
            }

            return $php;
        }

        $suffixes = DIRECTORY_SEPARATOR == '\\' ? (getenv('PATHEXT') ? explode(PATH_SEPARATOR, getenv('PATHEXT')) : array('.exe', '.bat', '.cmd', '.com')) : array('');
        foreach ($suffixes as $suffix) {
            if (is_executable($php = PHP_BINDIR.DIRECTORY_SEPARATOR.'php'.$suffix)) {
                return $php;
            }
        }

        if ($php = getenv('PHP_PEAR_PHP_BIN')) {
            if (is_executable($php)) {
                return $php;
            }
        }

        return $this->executableFinder->find('php');
    }

    /**
     * Finds the PHP executable
     *
     * @return string|null The PHP executable or null if it could not be found
     */
    public function find()
    {
        try {
            return $this->get();
        } catch (\Exception $ex) {
            return null;
        }
    }
}
