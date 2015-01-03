<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Asset;

use Symfony\Component\Asset\PackageInterface as AssetPackageInterface;

/**
 * Asset package interface.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 *
 * @deprecated since 2.7, to be removed in 3.0. Use the Asset Component instead.
 */
interface PackageInterface extends AssetPackageInterface
{
}
