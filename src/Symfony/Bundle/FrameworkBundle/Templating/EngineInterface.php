<?php

namespace Symfony\Bundle\FrameworkBundle\Templating;

use Symfony\Component\Templating\EngineInterface as BaseEngineInterface;
use Symfony\Component\HttpFoundation\Response;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * EngineInterface is the interface each engine must implement.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface EngineInterface extends BaseEngineInterface
{
    /**
     * Renders a view and returns a Response.
     *
     * @param string   $view       The view name
     * @param array    $parameters An array of parameters to pass to the view
     * @param Response $response   A Response instance
     *
     * @return Response A Response instance
     */
    function renderResponse($view, array $parameters = array(), Response $response = null);
}
