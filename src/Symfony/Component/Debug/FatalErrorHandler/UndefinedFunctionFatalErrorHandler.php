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

use Symfony\Component\ErrorHandler\FatalErrorHandler\UndefinedFunctionFatalErrorHandler as BaseUndefinedFunctionFatalErrorHandler;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.4, use "%s" instead.', UndefinedFunctionFatalErrorHandler::class, BaseUndefinedFunctionFatalErrorHandler::class), E_USER_DEPRECATED);

/**
 * @deprecated since Symfony 4.4, use Symfony\Component\ErrorHandler\FatalErrorHandler\UndefinedFunctionFatalErrorHandler instead.
 */
class UndefinedFunctionFatalErrorHandler extends BaseUndefinedFunctionFatalErrorHandler
{
}
