<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\VersionStrategy;

/**
 * Reads the version from a package.json file.
 *
 * For example, the manifest file might look like this:
 *     {
 *         "version": "1.0.0",
 *         "scripts": {
 *             "build": "npm run build:assets && npm run version:patch",
 *             "build:assets": "...",
 *             "version:patch": "npm version patch --git-tag-version false"
 *         }
 *     }
 */
class PackageJsonVersionStrategy implements VersionStrategyInterface
{
    private $packagePath;
    private $version;
    private $format;

    /**
     * @param string $packagePath Absolute path to the package.json file
     * @param string $format      Url format
     */
    public function __construct(string $packagePath, string $format = null)
    {
        $this->packagePath = $packagePath;
        $this->format = $format ?: '%s?%s';
    }

    public function getVersion($path)
    {
        return $this->loadVersion();
    }

    public function applyVersion($path)
    {
        $versionized = sprintf($this->format, ltrim($path, '/'), $this->loadVersion());

        if ($path && '/' == $path[0]) {
            return '/'.$versionized;
        }

        return $versionized;
    }

    private function loadVersion()
    {
        if (null === $this->version) {
            if (!file_exists($this->packagePath)) {
                throw new \RuntimeException(sprintf('Asset manifest file "%s" does not exist.', $this->packagePath));
            }

            $json = json_decode(file_get_contents($this->packagePath), true);

            if (0 < json_last_error() || !array_key_exists('version', $json)) {
                throw new \RuntimeException(sprintf('Error parsing JSON from asset package file "%s" - %s', $this->packagePath, json_last_error_msg()));
            }

            $this->version = $json['version'];
        }

        return $this->version;
    }
}
