<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Globals;

use Symfony\Polyfill\Intl\Icu\Icu;

/**
 * Provides fake static versions of the global functions in the intl extension.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 *
 * @deprecated since Symfony 5.3, use symfony/polyfill-intl-icu ^1.21 instead
 */
abstract class IntlGlobals
{
    /**
     * Indicates that no error occurred.
     */
    public const U_ZERO_ERROR = 0;

    /**
     * Indicates that an invalid argument was passed.
     */
    public const U_ILLEGAL_ARGUMENT_ERROR = 1;

    /**
     * Indicates that the parse() operation failed.
     */
    public const U_PARSE_ERROR = 9;

    /**
     * All known error codes.
     */
    private const ERROR_CODES = [
        self::U_ZERO_ERROR => 'U_ZERO_ERROR',
        self::U_ILLEGAL_ARGUMENT_ERROR => 'U_ILLEGAL_ARGUMENT_ERROR',
        self::U_PARSE_ERROR => 'U_PARSE_ERROR',
    ];

    /**
     * The error code of the last operation.
     */
    private static $errorCode = self::U_ZERO_ERROR;

    /**
     * The error code of the last operation.
     */
    private static $errorMessage = 'U_ZERO_ERROR';

    /**
     * Returns whether the error code indicates a failure.
     *
     * @param int $errorCode The error code returned by IntlGlobals::getErrorCode()
     */
    public static function isFailure(int $errorCode): bool
    {
        if (class_exists(Icu::class)) {
            return Icu::isFailure($errorCode);
        }

        trigger_deprecation('symfony/intl', '5.3', 'Polyfills are deprecated, try running "composer require symfony/polyfill-intl-icu ^1.21" instead.');

        return isset(self::ERROR_CODES[$errorCode])
            && $errorCode > self::U_ZERO_ERROR;
    }

    /**
     * Returns the error code of the last operation.
     *
     * Returns IntlGlobals::U_ZERO_ERROR if no error occurred.
     *
     * @return int
     */
    public static function getErrorCode()
    {
        if (class_exists(Icu::class)) {
            return Icu::getErrorCode();
        }

        trigger_deprecation('symfony/intl', '5.3', 'Polyfills are deprecated, try running "composer require symfony/polyfill-intl-icu ^1.21" instead.');

        return self::$errorCode;
    }

    /**
     * Returns the error message of the last operation.
     *
     * Returns "U_ZERO_ERROR" if no error occurred.
     */
    public static function getErrorMessage(): string
    {
        if (class_exists(Icu::class)) {
            return Icu::getErrorMessage();
        }

        trigger_deprecation('symfony/intl', '5.3', 'Polyfills are deprecated, try running "composer require symfony/polyfill-intl-icu ^1.21" instead.');

        return self::$errorMessage;
    }

    /**
     * Returns the symbolic name for a given error code.
     *
     * @param int $code The error code returned by IntlGlobals::getErrorCode()
     */
    public static function getErrorName(int $code): string
    {
        if (class_exists(Icu::class)) {
            return Icu::getErrorName($code);
        }

        trigger_deprecation('symfony/intl', '5.3', 'Polyfills are deprecated, try running "composer require symfony/polyfill-intl-icu ^1.21" instead.');

        return self::ERROR_CODES[$code] ?? '[BOGUS UErrorCode]';
    }

    /**
     * Sets the current error.
     *
     * @param int    $code    One of the error constants in this class
     * @param string $message The ICU class error message
     *
     * @throws \InvalidArgumentException If the code is not one of the error constants in this class
     */
    public static function setError(int $code, string $message = '')
    {
        if (class_exists(Icu::class)) {
            return Icu::setError($code, $message);
        }

        if (!isset(self::ERROR_CODES[$code])) {
            throw new \InvalidArgumentException(sprintf('No such error code: "%s".', $code));
        }

        self::$errorMessage = $message ? sprintf('%s: %s', $message, self::ERROR_CODES[$code]) : self::ERROR_CODES[$code];
        self::$errorCode = $code;
    }
}
