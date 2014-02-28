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

use Symfony\Component\Templating\Asset\PathPackage as BasePathPackage;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;

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
     * @param Request $requestStack The current request
     * @param string  $version      The version
     * @param string  $format       The version format
     */
    public function __construct(RequestStack $requestStack, $version = null, $format = null)
    {
        if ($requestStack->getCurrentRequest() instanceof Request) {
            $path = $requestStack->getCurrentRequest()->getBasePath();
        } else {
            $path = "/";
        }

        parent::__construct($path, $version, $format);
    }
}
