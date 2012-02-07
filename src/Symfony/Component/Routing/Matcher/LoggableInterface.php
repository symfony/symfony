<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher;

use Symfony\Component\Routing\Route;

/**
 * LoggableInterface allows logging UrlMatcher activity.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
interface LoggableInterface
{
    const ROUTE_DOES_NOT_MATCH = 0;
    const ROUTE_ALMOST_MATCHES = 1;
    const ROUTE_MATCHES        = 2;
    
    function log($msg, $level, $pathinfo = null, $name = null, Route $route = null);
}