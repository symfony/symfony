<?php

namespace Symfony\Component\Form\Test;

use Symfony\Component\Form\FormEvent;

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
