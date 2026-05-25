<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Error;

/**
 * Raised on shutdown when PHP exhausts its memory_limit.
 *
 * Instances bypass http_response_code() and header() calls in the default
 * renderer to avoid PHP 8.5+ warnings when adjusting response state after
 * the fatal error has fired.
 */
class OutOfMemoryError extends FatalError
{
}
