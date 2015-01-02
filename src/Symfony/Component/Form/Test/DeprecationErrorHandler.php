<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Test;

use Symfony\Component\Form\FormEvent;

/**
 * @deprecated since version 2.3, to be removed in 3.0.
 */
class DeprecationErrorHandler
{
    public static function handle($errorNumber, $message, $file, $line, $context)
    {
        if ($errorNumber & E_USER_DEPRECATED) {
            return true;
        }

        return \PHPUnit_Util_ErrorHandler::handleError($errorNumber, $message, $file, $line);
    }

    public static function handleBC($errorNumber, $message, $file, $line, $context)
    {
        if ($errorNumber & E_USER_DEPRECATED) {
            return true;
        }

        return false;
    }

    public static function preBind($listener, FormEvent $event)
    {
        set_error_handler(array('Symfony\Component\Form\Test\DeprecationErrorHandler', 'handle'));
        $listener->preBind($event);
        restore_error_handler();
    }
}
