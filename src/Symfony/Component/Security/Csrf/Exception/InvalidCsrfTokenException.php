<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * This exception is thrown when the csrf token is invalid.
 *
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
class InvalidCsrfTokenException extends BadRequestHttpException
{
}
