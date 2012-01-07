<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Exception;

/**
 * Exception thrown when hostname parameter doesn't match hostname from requirements
 *
 * @author Gunnar Lium <post@gunnarlium.com>
 *
 * @api
 */
class InvalidHostnameParameterException extends \InvalidArgumentException implements ExceptionInterface
{
}
