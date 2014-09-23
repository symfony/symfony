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

/**
 * ErrorHandler for undefined methods.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
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
            return;
        }

        $className = $matches[1];
        $methodName = $matches[2];

        $message = sprintf('Attempted to call method "%s" on class "%s".', $methodName, $className);

        $candidates = array();
        foreach (get_class_methods($className) as $definedMethodName) {
            $lev = levenshtein($methodName, $definedMethodName);
            if ($lev <= strlen($methodName) / 3 || false !== strpos($definedMethodName, $methodName)) {
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
