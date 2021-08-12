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
 * Disable version for all assets.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EmptyVersionStrategy implements VersionStrategyInterface
{
    /**
     * {@inheritdoc}
     */
    public function getVersion(string $path): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function applyVersion(string $path): string
    {
        return $path;
    }
}
