<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

/**
 * @internal use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait instead.
 */
trait ExpectDeprecationTraitForV8_4
{
    /**
     * @param string $message
     */
    public function expectDeprecation(): void
    {
        if (1 > func_num_args() || !\is_string($message = func_get_arg(0))) {
            throw new \InvalidArgumentException(sprintf('The "%s()" method requires the string $message argument.', __FUNCTION__));
        }

        if (!SymfonyTestsListenerTrait::$previousErrorHandler) {
            SymfonyTestsListenerTrait::$previousErrorHandler = set_error_handler([SymfonyTestsListenerTrait::class, 'handleError']);
        }

        SymfonyTestsListenerTrait::$expectedDeprecations[] = $message;
    }

    /**
     * @internal use expectDeprecation() instead.
     */
    public function expectDeprecationMessage(string $message): void
    {
        throw new \BadMethodCallException(sprintf('The "%s()" method is not supported by Symfony\'s PHPUnit Bridge ExpectDeprecationTrait, pass the message to expectDeprecation() instead.', __FUNCTION__));
    }

    /**
     * @internal use expectDeprecation() instead.
     */
    public function expectDeprecationMessageMatches(string $regularExpression): void
    {
        throw new \BadMethodCallException(sprintf('The "%s()" method is not supported by Symfony\'s PHPUnit Bridge ExpectDeprecationTrait.', __FUNCTION__));
    }
}
