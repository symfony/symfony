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
 * @since v2.2.0
 */
class DeprecationErrorHandler
{
    /**
     * @since v2.2.0
     */
    public static function handle($errorNumber, $message, $file, $line, $context)
    {
        if ($errorNumber & E_USER_DEPRECATED) {
            return true;
        }

        return \PHPUnit_Util_ErrorHandler::handleError($errorNumber, $message, $file, $line);
    }

    /**
     * @since v2.2.0
     */
    public static function handleBC($errorNumber, $message, $file, $line, $context)
    {
        if ($errorNumber & E_USER_DEPRECATED) {
            return true;
        }

        return false;
    }

    /**
     * @since v2.3.0
     */
    public static function preBind($listener, FormEvent $event)
    {
        set_error_handler(array('Symfony\Component\Form\Test\DeprecationErrorHandler', 'handle'));
        $listener->preBind($event);
        restore_error_handler();
    }
}
