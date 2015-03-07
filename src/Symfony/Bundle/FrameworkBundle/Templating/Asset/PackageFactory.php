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

trigger_error('The Symfony\Bundle\FrameworkBundle\Templating\Asset\PackageFactory is deprecated since version 2.7 and will be removed in 3.0. Use the Asset component instead.', E_USER_DEPRECATED);

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Templating\Asset\PackageInterface;

/**
 * Creates packages based on whether the current request is secure.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 *
 * @deprecated since 2.7, will be removed in 3.0. Use the Asset component instead.
 */
class PackageFactory
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns either the HTTP or SSL version of an asset package.
     *
     * @param Request $request The current request
     * @param string  $httpId  The id for the package to use when the current request is HTTP
     * @param string  $sslId   The id for the package to use when the current request is SSL
     *
     * @return PackageInterface The package
     */
    public function getPackage(Request $request, $httpId, $sslId)
    {
        return $this->container->get($request->isSecure() ? $sslId : $httpId);
    }
}
