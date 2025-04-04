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

use Symfony\Component\Process\Exception\PhpExecutableInvalidVersionException;
use Symfony\Component\Process\Exception\PhpExecutableNotFoundException;

/**
 * An executable finder specifically designed for the PHP executable.
 * This class allows searching for a specific version of PHP if provided.
 *
 * @author Pululu Kinaga Andre <pululuandre@gmail.com>
 */
class PhpSpecifExecutableFinder
{
    public function __construct(private readonly ExecutableFinder $executableFinder){}

    /**
     * Finds the PHP executable path, optionally for a specific version.
     *
     * @return string The path to the PHP executable.
     * @throws PhpExecutableNotFoundException If no executable is found.
     */
    public function find(string $version): string
    {

        if (!$this->isValidPhpVersion($version)) {
            throw new PhpExecutableInvalidVersionException("Invalid php version : ".$version);
        }

        $binary = "php{$version}";
        $commandResult = $this->executableFinder->find($binary);

        if (empty($commandResult)) {
            throw new PhpExecutableNotFoundException("PHP executable not found for the version : ".$version);
        }

        return trim($commandResult);
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
