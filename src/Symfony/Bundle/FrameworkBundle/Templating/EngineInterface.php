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
use Symfony\Component\Templating\EngineInterface as BaseEngineInterface;

/**
 * EngineInterface is the interface each engine must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
     *
     * @throws \RuntimeException if the template cannot be rendered
     */
    public function renderResponse($view, array $parameters = array(), Response $response = null);
}
