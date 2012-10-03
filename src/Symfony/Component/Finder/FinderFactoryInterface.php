<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder;

/**
 * Interface for creating new instances of Finder
 *
 * @author Beau Simensen <beau@dflydev.com>
 */
interface FinderFactoryInterface
{
    /**
     * Create Finder instance
     *
     * @return Finder
     */
    public function createFinder();
}
