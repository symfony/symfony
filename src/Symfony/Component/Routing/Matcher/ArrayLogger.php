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
 * ArrayLogger records matcher traces in an array.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
class ArrayLogger implements LoggableInterface
{
    protected $traces = array();

    public function log($msg, $level = self::ROUTE_DOES_NOT_MATCH, $pathinfo = null, $name = null, Route $route = null)
    {
        $this->traces[] = array(
            'log'     => $msg,
            'name'    => $name,
            'level'   => $level,
            'pattern' => null !== $route ? $route->getPattern() : null,
        );
    }

    public function getTraces()
    {
        return $this->traces;
    }
}