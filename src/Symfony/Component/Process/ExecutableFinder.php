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

    public function find($name)
    {
        $dirs = explode(PATH_SEPARATOR, getenv('PATH') ? getenv('PATH') : getenv('Path'));
        $suffixes = DIRECTORY_SEPARATOR == '\\' ? (getenv('PATHEXT') ? explode(PATH_SEPARATOR, getenv('PATHEXT')) : $this->suffixes) : array('');
        foreach ($suffixes as $suffix) {
            foreach ($dirs as $dir) {
                if (is_file($file = $dir.DIRECTORY_SEPARATOR.$name.$suffix) && is_executable($file)) {
                    return $file;
                }
            }
        }

        throw new ExecutableNotFoundException($name);
    }
}