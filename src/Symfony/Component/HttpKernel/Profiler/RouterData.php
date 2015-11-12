<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Profiler\ProfileData\ProfileDataInterface;

/**
 * RouterData.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class RouterData implements ProfileDataInterface
{
    private $redirect = false;
    private $url;
    private $route;

    /**
     * Constructor.
     *
     * @param Response $response    The Response.
     * @param string|null $route    The Route.
     */
    public function __construct(Response $response, $route = null)
    {
        if ($response instanceof RedirectResponse) {
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
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize(array($this->redirect, $this->route, $this->url));
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     */
    public function unserialize($serialized)
    {
        list($this->redirect, $this->route, $this->url) = unserialize($serialized);
    }

    public function getName()
    {
        return "router";
    }
}
