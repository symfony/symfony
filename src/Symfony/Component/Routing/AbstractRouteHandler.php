<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

use Symfony\Component\Routing\Route;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractRouteHandler
{
    /**
     * {@inheritdoc}
     */
    public function updateBeforeCompilation(Route $route)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function checkMatcherExceptions(Route $route)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function updateMatchedParameters(Route $route, array $parameters)
    {
        return $parameters;
    }
}
