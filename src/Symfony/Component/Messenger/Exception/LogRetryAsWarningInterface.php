<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

/**
 * Marker interface for exceptions to indicate a retry should be logged as warning instead of error.
 *
 * @author Joris Steyn <j.steyn@hoffelijk.nl>
 */
interface LogRetryAsWarningInterface extends \Throwable
{
}
