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
 * A simple implementation of FinderFactoryInterface.
 *
 * @author Beau Simensen <beau@dflydev.com>
 */
class FinderFactory implements FinderFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createFinder()
    {
        return new Finder;
    }
}
