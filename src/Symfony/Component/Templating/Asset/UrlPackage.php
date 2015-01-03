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

use Symfony\Component\Asset\UrlPackage as AssetUrlPackage;

/**
 * The URL packages adds a version and a base URL to asset URLs.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 *
 * @deprecated since 2.7, to be removed in 3.0. Use the Asset Component instead.
 */
class UrlPackage extends AssetUrlPackage implements PackageInterface
{
}
