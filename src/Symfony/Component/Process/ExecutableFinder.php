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
 * Generic executable finder.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ExecutableFinder
{
    private $suffixes = array('.exe', '.bat', '.cmd', '.com');

    public function setSuffixes(array $suffixes)
    {
        $this->suffixes = $suffixes;
    }

    public function addSuffix($suffix)
    {
        $this->suffixes[] = $suffix;
    }

    /**
     * Finds an executable by name.
     *
     * @param string $name    The executable name (without the extension)
     *
     * @throws ExecutableNotFoundException
     *
     * @return string The executable path or default value
     */
    public function get($name)
    {
        $dirs = explode(PATH_SEPARATOR, getenv('PATH') ? getenv('PATH') : getenv('Path'));
        $suffixes = DIRECTORY_SEPARATOR == '\\' ? (getenv('PATHEXT') ? explode(PATH_SEPARATOR, getenv('PATHEXT')) : $this->suffixes) : array('');
        $firstFound = null;
        foreach ($suffixes as $suffix) {
            foreach ($dirs as $dir) {
                if (is_file($file = $dir.DIRECTORY_SEPARATOR.$name.$suffix)) {
                    if (is_executable($file)) {
                        return $file;
                    }

                    if (null !== $firstFound) {
                        $firstFound = $file;
                    }
                }
            }
        }

        if (null !== $firstFound) {
            throw new FileNotExecutableException($firstFound);
        }

        throw new ExecutableNotFoundException($name);
    }

    /**
     * Finds an executable by name
     *
     * @param string $name
     * @param string $default The default to return if no executable is found
     *
     * @return string
     */
    public function find($name, $default = null)
    {
        try {
            return $this->get($name);
        } catch (\Exception $ex) {
            return $default;
        }
    }
}
