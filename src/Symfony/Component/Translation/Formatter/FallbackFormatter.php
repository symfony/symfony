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

use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Component\Translation\Exception\InvalidArgumentException;

class FallbackFormatter implements MessageFormatterInterface, ChoiceMessageFormatterInterface
{
    /**
     * @var MessageFormatterInterface|ChoiceMessageFormatterInterface
     */
    private $firstFormatter;

    /**
     * @var MessageFormatterInterface|ChoiceMessageFormatterInterface
     */
    private $secondFormatter;

    public function __construct(MessageFormatterInterface $firstFormatter, MessageFormatterInterface $secondFormatter)
    {
        $this->firstFormatter = $firstFormatter;
        $this->secondFormatter = $secondFormatter;
    }

    public function format($message, $locale, array $parameters = array())
    {
        try {
            $result = $this->firstFormatter->format($message, $locale, $parameters);
        } catch (InvalidArgumentException $e) {
            return $this->secondFormatter->format($message, $locale, $parameters);
        }

        if ($result === $message) {
            $result = $this->secondFormatter->format($message, $locale, $parameters);
        }

        return $result;
    }

    public function choiceFormat($message, $number, $locale, array $parameters = array())
    {
        // If both support ChoiceMessageFormatterInterface
        if ($this->firstFormatter instanceof ChoiceMessageFormatterInterface && $this->secondFormatter instanceof ChoiceMessageFormatterInterface) {
            try {
                $result = $this->firstFormatter->choiceFormat($message, $number, $locale, $parameters);
            } catch (InvalidArgumentException $e) {
                return $this->secondFormatter->choiceFormat($message, $number, $locale, $parameters);
            }

            if ($result === $message) {
                $result = $this->secondFormatter->choiceFormat($message, $number, $locale, $parameters);
            }

            return $result;
        }

        if ($this->firstFormatter instanceof ChoiceMessageFormatterInterface) {
            return $this->firstFormatter->choiceFormat($message, $number, $locale, $parameters);
        }

        if ($this->secondFormatter instanceof ChoiceMessageFormatterInterface) {
            return $this->secondFormatter->choiceFormat($message, $number, $locale, $parameters);
        }

        throw new LogicException(sprintf('No formatters support plural translations.'));
    }
}
