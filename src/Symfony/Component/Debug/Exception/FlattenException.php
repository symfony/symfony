<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Exception;

use Symfony\Component\ErrorCatcher\Exception\FlattenException as BaseFlattenException;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.4, use "%s" instead.', FlattenException::class, BaseFlattenException::class), E_USER_DEPRECATED);

/**
 * @deprecated since Symfony 4.4, use Symfony\Component\ErrorCatcher\Exception\FlattenException instead.
 */
class FlattenException extends BaseFlattenException
{
    /**
     * @deprecated since Symfony 4.4, use Symfony\Component\ErrorHandler\Exception::createFromThrowable() instead.
     */
    public static function create(\Exception $exception, $statusCode = null, array $headers = []): self
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.4, use Symfony\Component\ErrorHandler\Exception::createFromThrowable() instead.', __METHOD__), E_USER_DEPRECATED);

        return parent::createFromThrowable($exception, $statusCode, $headers);
    }
}
