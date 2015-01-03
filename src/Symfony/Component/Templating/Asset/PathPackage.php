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

use Symfony\Component\Asset\PathPackage as AssetPathPackage;

/**
 * The path packages adds a version and a base path to asset URLs.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 *
 * @deprecated since 2.7, to be removed in 3.0. Use the Asset Component instead.
 */
class PathPackage extends AssetPathPackage implements PackageInterface
{
}
