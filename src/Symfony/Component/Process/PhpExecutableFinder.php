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
     * Finds the PHP executable.
     *
     * @return string|false The PHP executable path or false if it cannot be found
     */
    public function find(bool $includeArgs = true)
    {
        if ($php = getenv('PHP_BINARY')) {
            if (!is_executable($php)) {
                $command = '\\' === \DIRECTORY_SEPARATOR ? 'where' : 'command -v';
                if ($php = strtok(exec($command.' '.escapeshellarg($php)), \PHP_EOL)) {
                    if (!is_executable($php)) {
                        return false;
                    }
                } else {
                    return false;
                }
            }

            return $php;
        }

        $args = $this->findArguments();
        $args = $includeArgs && $args ? ' '.implode(' ', $args) : '';

        // PHP_BINARY return the current sapi executable
        if (\PHP_BINARY && \in_array(\PHP_SAPI, ['cgi-fcgi', 'cli', 'cli-server', 'phpdbg'], true)) {
            return \PHP_BINARY.$args;
        }

        if ($php = getenv('PHP_PATH')) {
            if (!@is_executable($php)) {
                return false;
            }

            return $php;
        }

        if ($php = getenv('PHP_PEAR_PHP_BIN')) {
            if (@is_executable($php)) {
                return $php;
            }
        }

        return $this->findByName('php') ?? false;
    }

    /**
     * Finds the PHP executable by a specific name.
     *
     * @param string $name
     * @return string|null The PHP executable path or NULL if it cannot be found
     */
    public function findByName(string $name): ?string
    {
        if (@is_executable($php = \PHP_BINDIR.('\\' === \DIRECTORY_SEPARATOR ? '\\'.$name.'.exe' : '/'.$name))) {
            return $php;
        }

        $dirs = [\PHP_BINDIR];
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $dirs[] = 'C:\xampp\php\\';
        }

        return $this->executableFinder->find($name, null, $dirs);
    }

    /**
     * Finds a PHP executable with one of the given names.
     *
     * @param string[] $names
     * @return string|null The PHP executable path or NULL if it cannot be found
     */
    public function tryNames(array $names): ?string
    {
        foreach ($names as $name) {
            if ($php = $this->findByName($name)) {
                return $php;
            }
        }

        return null;
    }

    /**
     * Finds the PHP executable by a specific version.
     *
     * @param string $version
     * @return string|null The PHP executable path or NULL if it cannot be found
     */
    public function findByVersion(string $version)
    {
        $versionWithoutDots = str_replace('.', '', $version);
        $names = ['php'.$versionWithoutDots];

        if ($version !== $versionWithoutDots) {
            $names[] = 'php'.$version;
        }

        return $this->tryNames($names);
    }

    /**
     * Finds a PHP executable in one of the given versions.
     *
     * @param string[] $versions
     * @return string|null The PHP executable path or NULL if it cannot be found
     */
    public function tryVersions(array $versions)
    {
        foreach ($versions as $version) {
            if ($php = $this->findByVersion($version)) {
                return $php;
            }
        }

        return null;
    }

    /**
     * Finds the PHP executable arguments.
     *
     * @return array The PHP executable arguments
     */
    public function findArguments()
    {
        $arguments = [];
        if ('phpdbg' === \PHP_SAPI) {
            $arguments[] = '-qrr';
        }

        return $arguments;
    }
}
