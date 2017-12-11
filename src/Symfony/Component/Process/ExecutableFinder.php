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
 * Generic executable finder.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ExecutableFinder
{
    private $suffixes = array('.exe', '.bat', '.cmd', '.com');

    /**
     * Replaces default suffixes of executable.
     */
    public function setSuffixes(array $suffixes)
    {
        $this->suffixes = $suffixes;
    }

    /**
     * Adds new possible suffix to check for executable.
     */
    public function addSuffix(string $suffix)
    {
        $this->suffixes[] = $suffix;
    }

    public function find(string $name, ?string $default = null, array $extraDirs = array()): ?string
    {
        if (ini_get('open_basedir')) {
            $searchPath = explode(PATH_SEPARATOR, ini_get('open_basedir'));
            $dirs = array();
            foreach ($searchPath as $path) {
                // Silencing against https://bugs.php.net/69240
                if (@is_dir($path)) {
                    $dirs[] = $path;
                } else {
                    if (basename($path) == $name && @is_executable($path)) {
                        return $path;
                    }
                }
            }
        } else {
            $dirs = array_merge(
                explode(PATH_SEPARATOR, getenv('PATH') ?: getenv('Path')),
                $extraDirs
            );
        }

        $suffixes = array('');
        if ('\\' === DIRECTORY_SEPARATOR) {
            $pathExt = getenv('PATHEXT');
            $suffixes = array_merge($suffixes, $pathExt ? explode(PATH_SEPARATOR, $pathExt) : $this->suffixes);
        }
        foreach ($suffixes as $suffix) {
            foreach ($dirs as $dir) {
                if (@is_file($file = $dir.DIRECTORY_SEPARATOR.$name.$suffix) && ('\\' === DIRECTORY_SEPARATOR || is_executable($file))) {
                    return $file;
                }
            }
        }

        return $default;
    }
}
