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

use Symfony\Component\Debug\Exception\FatalErrorException;
use Symfony\Component\Debug\Exception\UndefinedMethodException;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.4, use "%s" instead.', UndefinedMethodFatalErrorHandler::class, \Symfony\Component\ErrorHandler\ErrorEnhancer\UndefinedMethodErrorEnhancer::class), \E_USER_DEPRECATED);

/**
 * ErrorHandler for undefined methods.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @deprecated since Symfony 4.4, use Symfony\Component\ErrorHandler\ErrorEnhancer\UndefinedMethodErrorEnhancer instead.
 */
class UndefinedMethodFatalErrorHandler implements FatalErrorHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handleError(array $error, FatalErrorException $exception)
    {
        preg_match('/^Call to undefined method (.*)::(.*)\(\)$/', $error['message'], $matches);
        if (!$matches) {
            return null;
        }

        $className = $matches[1];
        $methodName = $matches[2];

        $message = sprintf('Attempted to call an undefined method named "%s" of class "%s".', $methodName, $className);

        if (!class_exists($className) || null === $methods = get_class_methods($className)) {
            // failed to get the class or its methods on which an unknown method was called (for example on an anonymous class)
            return new UndefinedMethodException($message, $exception);
        }

        $candidates = [];
        foreach ($methods as $definedMethodName) {
            $lev = levenshtein($methodName, $definedMethodName);
            if ($lev <= \strlen($methodName) / 3 || false !== strpos($definedMethodName, $methodName)) {
                $candidates[] = $definedMethodName;
            }
        }

        if ($candidates) {
            sort($candidates);
            $last = array_pop($candidates).'"?';
            if ($candidates) {
                $candidates = 'e.g. "'.implode('", "', $candidates).'" or "'.$last;
            } else {
                $candidates = '"'.$last;
            }

            $message .= "\nDid you mean to call ".$candidates;
        }

        return new UndefinedMethodException($message, $exception);
    }
}
