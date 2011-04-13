<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Configurator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Templating\Helper\AssetsHelper;

/**
 * Configures asset helper packages based on the current request.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class AssetsHelperConfigurator
{
    private $container;
    private $request;

    /**
     * Constructor.
     *
     * @param Request $request  The current request
     * @param array   $httpUrls Base URLs for HTTP requests
     * @param array   $sslUrls  Base URLs for SSL requests
     */
    public function __construct(ContainerInterface $container, Request $request)
    {
        $this->container = $container;
        $this->request   = $request;
    }

    /**
     * Configures the asset helper base URLs based on request security.
     *
     * @param AssetsHelper $helper The helper
     */
    public function configure(AssetsHelper $helper)
    {
        $id = $this->request->isSecure() ? 'templating.asset_packages.ssl' : 'templating.asset_packages.http';

        foreach ($this->container->get($id) as $name => $package) {
            $helper->addPackage($name, $package);
        }
    }
}
