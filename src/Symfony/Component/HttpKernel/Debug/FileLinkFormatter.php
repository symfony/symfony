<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Debug;

use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter as ErrorHandlerFileLinkFormatter;

trigger_deprecation('symfony/http-kernel', '6.4', 'The "%s" class is deprecated, use "%s" instead.', FileLinkFormatter::class, ErrorHandlerFileLinkFormatter::class);

class_exists(ErrorHandlerFileLinkFormatter::class);

if (!class_exists(FileLinkFormatter::class, false)) {
    class_alias(ErrorHandlerFileLinkFormatter::class, FileLinkFormatter::class);
}

if (false) {
    /**
     * @deprecated since Symfony 6.4, use FileLinkFormatter from the ErrorHandler component instead
     */
    class FileLinkFormatter
    {
    }
}
