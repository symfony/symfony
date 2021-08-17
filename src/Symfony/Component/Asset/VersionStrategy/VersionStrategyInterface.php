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
 * Asset version strategy interface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface VersionStrategyInterface
{
    /**
     * Returns the asset version for an asset.
     *
     * @return string
     */
    public function getVersion(string $path);

    /**
     * Applies version to the supplied path.
     *
     * @return string
     */
    public function applyVersion(string $path);
}
