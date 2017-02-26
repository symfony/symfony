<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating;

use Symfony\Component\HttpFoundation\Response;

/**
 * Represents a template reference.
 *
 * @author Pierre du Plessis <pdples@gmail.com>
 */
class Template
{
    private $template;

    private $parameters;

    private $statusCode;

    private $headers;

    public function __construct($template, array $parameters = array(), $statusCode = Response::HTTP_OK, array $headers = array())
    {
        $this->template = $template;
        $this->parameters = $parameters;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}
