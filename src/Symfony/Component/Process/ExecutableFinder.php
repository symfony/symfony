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
    private $suffixes = ['.exe', '.bat', '.cmd', '.com'];

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

    /**
     * Finds an executable by name.
     *
     * @param string      $name      The executable name (without the extension)
     * @param string|null $default   The default to return if no executable is found
     * @param array       $extraDirs Additional dirs to check into
     *
     * @return string|null
     */
    public function find(string $name, ?string $default = null, array $extraDirs = [])
    {
        $dirs = array_merge(
            explode(\PATH_SEPARATOR, getenv('PATH') ?: getenv('Path')),
            $extraDirs
        );

        $suffixes = [''];
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $pathExt = getenv('PATHEXT');
            $suffixes = array_merge($pathExt ? explode(\PATH_SEPARATOR, $pathExt) : $this->suffixes, $suffixes);
        }
        foreach ($suffixes as $suffix) {
            foreach ($dirs as $dir) {
                if (@is_file($file = $dir.\DIRECTORY_SEPARATOR.$name.$suffix) && ('\\' === \DIRECTORY_SEPARATOR || @is_executable($file))) {
                    return $file;
                }

                if (!@is_dir($dir) && basename($dir) === $name.$suffix && @is_executable($dir)) {
                    return $dir;
                }
            }
        }

        $command = '\\' === \DIRECTORY_SEPARATOR ? 'where' : 'command -v --';
        if (\function_exists('exec') && ($executablePath = strtok(@exec($command.' '.escapeshellarg($name)), \PHP_EOL)) && @is_executable($executablePath)) {
            return $executablePath;
        }

        return $default;
    }
}
