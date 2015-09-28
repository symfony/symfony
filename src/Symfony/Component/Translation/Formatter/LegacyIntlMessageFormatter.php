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
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 */
class LegacyIntlMessageFormatter implements MessageFormatterInterface
{
    /**
     * {@inheritdoc}
     */
    public function format($locale, $id, array $parameters = array())
    {
        if (!$parameters) {
            return $id;
        }

        $formatter = new \MessageFormatter($locale, $id);
        if (null === $formatter) {
            return $this->fallbackToLegacyFormatter($id, $parameters);
        }

        $message = $formatter->format($parameters);
        if ($formatter->getErrorCode() !== U_ZERO_ERROR) {
            return $this->fallbackToLegacyFormatter($message, $parameters);
        }

        if (!$formatter->parse($message) && $formatter->getErrorCode() === U_ZERO_ERROR) {
            return $this->fallbackToLegacyFormatter($message, $parameters);
        }

        return $message;
    }

    private function fallbackToLegacyFormatter($message, $parameters)
    {
        return strtr($message, $parameters);
    }
}
