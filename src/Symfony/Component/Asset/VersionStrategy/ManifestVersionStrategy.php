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
 * Returns the version according to a manifest.
 *
 * @author Paulo Rodrigues Pinto <regularjack@gmail.com>
 */
class ManifestVersionStrategy implements VersionStrategyInterface
{
    private $manifest;

    /**
     * @param array $manifest Asset manifest
     */
    public function __construct(array $manifest)
    {
        $this->manifest = $manifest;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion($path)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function applyVersion($path)
    {
        if (array_key_exists($path, $this->manifest)) {
            return $this->manifest[$path];
        }

        return $path;
    }
}
