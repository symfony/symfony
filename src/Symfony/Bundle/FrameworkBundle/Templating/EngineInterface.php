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

@trigger_error('The '.EngineInterface::class.' interface is deprecated since version 4.3 and will be removed in 5.0; use Twig instead.', \E_USER_DEPRECATED);

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\EngineInterface as BaseEngineInterface;

/**
 * EngineInterface is the interface each engine must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 4.3, to be removed in 5.0; use Twig instead.
 */
interface EngineInterface extends BaseEngineInterface
{
    /**
     * Renders a view and returns a Response.
     *
     * @param string $view       The view name
     * @param array  $parameters An array of parameters to pass to the view
     *
     * @return Response A Response instance
     *
     * @throws \RuntimeException if the template cannot be rendered
     */
    public function renderResponse($view, array $parameters = [], Response $response = null);
}
