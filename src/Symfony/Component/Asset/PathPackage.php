<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset;

use Symfony\Component\Asset\Context\ContextInterface;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * Package that adds a base path to asset URLs in addition to a version.
 *
 * In addition to the provided base path, this package also automatically
 * prepends the current request base path if a Context is available to
 * allow a website to be hosted easily under any given path under the Web
 * Server root directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PathPackage extends Package
{
    private string $basePath;

    /**
     * @param string $basePath The base path to be prepended to relative paths
     */
    public function __construct(string $basePath, VersionStrategyInterface $versionStrategy, ContextInterface $context = null)
    {
        parent::__construct($versionStrategy, $context);

        if (!$basePath) {
            $this->basePath = '/';
        } else {
            if ('/' != $basePath[0]) {
                $basePath = '/'.$basePath;
            }

            $this->basePath = rtrim($basePath, '/').'/';
        }
    }

    public function getUrl(string $path): string
    {
        $versionedPath = parent::getUrl($path);

        // if absolute or begins with /, we're done
        if ($this->isAbsoluteUrl($versionedPath) || ($versionedPath && '/' === $versionedPath[0])) {
            return $versionedPath;
        }

        return $this->getBasePath().ltrim($versionedPath, '/');
    }

    /**
     * Returns the base path.
     */
    public function getBasePath(): string
    {
        return $this->getContext()->getBasePath().$this->basePath;
    }
}
