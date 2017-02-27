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
 * @author Pierre du Plessis <pdples@gmail.com>
 */
class TemplatedResponse implements TemplatedResponseInterface
{
    private $template;
    private $parameters;
    private $response;

    public function __construct($template, array $parameters = array(), Response $response = null)
    {
        $this->template = $template;
        $this->parameters = $parameters;
        $this->response = $response ?: new Response();
    }

    /**
     * {@inheritdoc}
     */
    public function getResponse(EngineInterface $templating)
    {
        return $templating->renderResponse($this->template, $this->parameters, $this->response);
    }
}
