<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Asset;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Templating\Asset\PathPackage as BasePathPackage;

/**
 * The path packages adds a version and a base path to asset URLs.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class PathPackage extends BasePathPackage
{
    /**
     * Constructor.
     *
     * @param Request $request The current request
     * @param string  $version The version
     * @param string  $format  The version format
     */
    public function __construct(Request $request, $version = null, $format = null)
    {
        parent::__construct($request->getBasePath(), $version, $format);
    }
}
