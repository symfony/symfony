<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Exception;

/**
 * Base RuntimeException for the Security component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RuntimeException extends \RuntimeException implements ExceptionInterface
{
}
