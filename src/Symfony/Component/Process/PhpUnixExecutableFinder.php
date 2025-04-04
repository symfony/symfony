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

use Symfony\Component\Process\Contracts\CommandExecutorInterface;
use Symfony\Component\Process\Contracts\ExecutableFinderInterface;
use Symfony\Component\Process\Exception\PhpUnixExecutableInvalidVersionException;
use Symfony\Component\Process\Exception\PhpUnixExecutableNotFoundException;

/**
 * An executable finder specifically designed for the PHP executable on Unix-based systems.
 * This class allows searching for a specific version of PHP if provided.
 *
 * @author Pululu Kinaga Andre <pululuandre@gmail.com>
 */
class PhpUnixExecutableFinder
{
    /**
     * Constructor for the class.
     *
     * @param ExecutableFinderInterface|null $defaultExecutableFinder An optional default executable finder.
     */
    public function __construct(
        private readonly CommandExecutorInterface $commandExecutor,
        private readonly ?ExecutableFinderInterface $defaultExecutableFinder = null,
    ) {}

    /**
     * Finds the PHP executable path, optionally for a specific version.
     *
     * @param string|null $version The PHP version to search for (e.g., "8.3"), or null for the default.
     * @param bool $includeArgs args
     * @return string The path to the PHP executable.
     * @throws PhpUnixExecutableNotFoundException If no executable is found.
     */
    public function find(?string $version = null, bool $includeArgs = true): string
    {

        if (!empty($version) && !$this->isValidPhpVersion($version)) {
            throw new PhpUnixExecutableInvalidVersionException("Invalid php version : ".$version);
        }

        if (null === $version && $this->defaultExecutableFinder) {
            $default = $this->defaultExecutableFinder->find($includeArgs);

            if (!is_string($default)) {
                throw new PhpUnixExecutableNotFoundException("PHP default executable not found");
            }

            return $default;
        }

        $binary = $version ? "php{$version}" : "php";

        // Try finding the PHP executable using command -v
        $path = trim($this->commandExecutor->execute("command -v " . $binary));

        if ($this->isValidPhpExecutable($path)) {
            return $path;
        }

        throw new PhpUnixExecutableNotFoundException("PHP executable not found for the given version");
    }

    /**
     * Checks if a given path is a valid PHP executable.
     *
     * @param string $path The path to check.
     * @return bool True if the path is a valid executable, false otherwise.
     */
    private function isValidPhpExecutable(string $path): bool
    {
        return !empty($path) && file_exists($path) && is_executable($path);
    }

    /**
     * Checks if a string is a valid PHP version.
     *
     * A valid PHP version is in the format `major.minor.patch`, where:
     * - `major`, `minor`, and `patch` are numeric values.
     * - An optional pre-release label (e.g., `-beta`, `-alpha`) and build metadata (e.g., `+build1`) may also be included.
     *
     * This function also supports versions with or without a leading "v" (e.g., `v7.4.0` is valid).
     *
     * @param string $version The version string to check.
     *
     * @return bool Returns `true` if the version string is a valid PHP version, otherwise `false`.
     */
    private function isValidPhpVersion(string $version): bool
    {
        if (str_starts_with($version, 'v')) {
            $version = substr($version, 1);
        }

        return preg_match('/^\d+\.\d+(\.\d+)?(-[a-zA-Z0-9\-\.]+)?$/', $version) === 1;
    }
}
