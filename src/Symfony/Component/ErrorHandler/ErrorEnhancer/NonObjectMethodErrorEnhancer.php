<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\ErrorEnhancer;

use Symfony\Component\ErrorHandler\Error\FatalError;
use Symfony\Component\ErrorHandler\Error\NonObjectMethodError;

/**
 * Class NonObjectMethodErrorEnhancer.
 */
class NonObjectMethodErrorEnhancer implements ErrorEnhancerInterface
{
    /**
     * {@inheritdoc}
     */
    public function enhance(\Throwable $error): ?\Throwable
    {
        if ($error instanceof FatalError) {
            return null;
        }

        $message = $error->getMessage();
        preg_match('/^Call to a member function (.*\(\)) on (.*)$/', $message, $matches);
        if (!$matches) {
            return null;
        }

        $methodName = $matches[1];
        $objectTypeName = $matches[2];

        if (!\in_array($objectTypeName, ['null', 'array'])) {
            return null;
        }

        $messageTemplate = $this->prepareMessage($objectTypeName);

        $line = @file($error->getFile())[$error->getLine() - 1] ?? false;
        if (!$line) {
            return null;
        }

        $expression = $this->extractExpression($line, $methodName);
        if (!$expression) {
            return null;
        }

        $message = sprintf($messageTemplate, $methodName, $expression);

        return new NonObjectMethodError($message, $error);
    }

    private function prepareMessage(string $objectTypeName): string
    {
        if ('array' === $objectTypeName) {
            return 'Attempted to call method "%s" of expression "%s", which contains a non object, but an array.';
        }

        return 'Attempted to call method "%s" of expression "%s", which contains a non object, but a null.';
    }

    private function extractExpression(string $line, string $methodName): string
    {
        $pattern = '/(((self|static|[\w]+)::(([\w\(\s\$\,\)\"\'\-\>]+)|(\$[\w\-\>]+)))|(\$[\w\(\s\,\)\"\'\-\>]+))\-\>%s/';

        $patternExpression = sprintf($pattern, $methodName);

        preg_match($patternExpression, $line, $matches);

        if (!$matches) {
            return '';
        }

        return $matches[1];
    }
}
