<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Formatter;

/**
 * IntlMessageFormatter.
 *
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 *
 * @api
 */
class IntlMessageFormatter implements MessageFormatterInterface
{
    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function format($locale, $id, $number = null, array $arguments = array())
    {
        if ($number !== null) {
            array_unshift($arguments, $number);
        }

        $formatter = new \MessageFormatter($locale, $id);

        if ( ! $formatter) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid message format. Reason: %s (error #%d)',
                    $formatter->getErrorMessage(),
                    $formatter->getErrorCode()
                )
            );
        }

        $message = $formatter->format($arguments);

        if ($formatter->getErrorCode() !== U_ZERO_ERROR) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unable to format message. Reason: %s (error #%s)',
                    $formatter->getErrorMessage(),
                    $formatter->getErrorCode()
                )
            );
        }

        return $message;
    }
}
