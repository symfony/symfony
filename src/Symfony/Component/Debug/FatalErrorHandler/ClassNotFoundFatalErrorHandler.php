<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\FatalErrorHandler;

use Symfony\Component\ErrorHandler\FatalErrorHandler\ClassNotFoundFatalErrorHandler as BaseClassNotFoundFatalErrorHandler;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.4, use "%s" instead.', ClassNotFoundFatalErrorHandler::class, BaseClassNotFoundFatalErrorHandler::class), E_USER_DEPRECATED);

/**
 * @deprecated since Symfony 4.4, use Symfony\Component\ErrorHandler\FatalErrorHandler\ClassNotFoundFatalErrorHandler instead.
 */
class ClassNotFoundFatalErrorHandler extends BaseClassNotFoundFatalErrorHandler
{
}
