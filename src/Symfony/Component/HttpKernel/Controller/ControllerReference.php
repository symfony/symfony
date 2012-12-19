<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

/**
 * ControllerReference.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ControllerReference
{
    public $controller;
    public $attributes = array();
    public $query = array();

    public function __construct($controller, array $attributes = array(), array $query = array())
    {
        $this->controller = $controller;
        $this->attributes = $attributes;
        $this->query = $query;
    }
}
