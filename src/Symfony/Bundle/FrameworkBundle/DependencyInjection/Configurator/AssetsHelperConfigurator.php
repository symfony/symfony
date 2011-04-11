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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Templating\Helper\AssetsHelper;

/**
 * Configures asset helper packages based on the current request.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class AssetsHelperConfigurator
{
    private $request;
    private $httpUrls;
    private $sslUrls;

    /**
     * Constructor.
     *
     * @param Request $request  The current request
     * @param array   $httpUrls Base URLs for HTTP requests
     * @param array   $sslUrls  Base URLs for SSL requests
     */
    public function __construct(Request $request, array $httpUrls, array $sslUrls)
    {
        $this->request  = $request;
        $this->httpUrls = $httpUrls;
        $this->sslUrls  = $sslUrls;
    }

    /**
     * Configures the asset helper base URLs based on request security.
     *
     * @param AssetsHelper $helper The helper
     */
    public function configure(AssetsHelper $helper)
    {
        $helper->addBaseUrlPackages($this->request->isSecure() ? $this->sslUrls : $this->httpUrls);
    }
}
