<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Factory;

use Assetic\Factory\AssetFactory as BaseAssetFactory;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Loads asset formulae from the filesystem.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class AssetFactory extends BaseAssetFactory
{
    protected $kernel;

    public function __construct(KernelInterface $kernel, $baseDir, $debug = false)
    {
        $this->kernel = $kernel;

        parent::__construct($baseDir, $debug);
    }

    /**
     * Adds support for bundle notation and globs.
     *
     * Please note this is a naive implementation of globs in that it doesn't
     * attempt to support bundle inheritance within the glob pattern itself.
     */
    protected function parseInput($input)
    {
        // expand bundle notation
        if ('@' == $input[0] && false !== strpos($input, '/')) {
            if (false !== $pos = strpos($input, '*')) {
                // locateResource() does not support globs so we provide a naive implementation here
                list($before, $after) = explode('*', $input, 2);
                $input = $this->kernel->locateResource($before).'*'.$after;
            } else {
                $input = $this->kernel->locateResource($input);
            }
        }

        return parent::parseInput($input);
    }
}
