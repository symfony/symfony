<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Exception;

/**
 * Base exception class for not implemented behaviors of the intl extension in the Locale component.
 *
 * @author Eriksen Costa <eriksen.costa@infranology.com.br>
 *
 * @deprecated since Symfony 5.3, use symfony/polyfill-intl-icu ^1.21 instead
 */
class NotImplementedException extends RuntimeException
{
    public const INTL_INSTALL_MESSAGE = 'Please install the "intl" extension for full localization capabilities.';

    /**
     * @param string $message The exception message. A note to install the intl extension is appended to this string
     */
    public function __construct(string $message)
    {
        parent::__construct($message.' '.self::INTL_INSTALL_MESSAGE);
    }
}
