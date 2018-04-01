<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Translation\Exception;

/**
 * Thrown when a resource does not exist.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class NotFoundResourceException extends \InvalidArgumentException implements ExceptionInterface
{
}
