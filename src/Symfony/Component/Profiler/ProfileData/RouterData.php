<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\ProfileData;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class RouterData
 * @package Symfony\Component\Profiler\ProfileData
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class RouterData
{
    private $redirect = false;
    private $url;
    private $route;

    public function __construct(Response $response, $route = null)
    {
        if ( $response instanceof RedirectResponse ) {
            $this->redirect = true;
            $this->url = $response->getTargetUrl();
        }
        $this->route = $route;
    }

    /**
     * @return bool Whether this request will result in a redirect
     */
    public function getRedirect()
    {
        return $this->redirect;
    }

    /**
     * @return string|null The target URL
     */
    public function getTargetUrl()
    {
        return $this->url;
    }

    /**
     * @return string|null The target route
     */
    public function getTargetRoute()
    {
        return $this->route;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'router';
    }
}