<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Exception;

/**
 * Exception thrown when a route does not exist.
 *
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class RouteNotFoundException extends \InvalidArgumentException implements ExceptionInterface
{
}
