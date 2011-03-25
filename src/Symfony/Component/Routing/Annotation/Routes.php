<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Annotation;

/**
 * Annotation class for @Routes().
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Routes
{
    private $routes;

    /**
     * Constructor.
     *
     * @param array $data An array of key/value parameters.
     */
    public function __construct(array $data)
    {
        if (!isset($data['value']) || !is_array($data['value'])) {
            throw new \LogicException('A @Routes annotation must have an array of @Route annotation as argument.');
        }

        $this->routes = $data['value'];
    }

    public function setRoutes($routes)
    {
        $this->routes = $routes;
    }

    public function getRoutes()
    {
        return $this->routes;
    }
}
