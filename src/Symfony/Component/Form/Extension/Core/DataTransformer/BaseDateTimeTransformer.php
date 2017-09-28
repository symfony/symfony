<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

abstract class BaseDateTimeTransformer implements DataTransformerInterface
{
    protected static $formats = array(
        \IntlDateFormatter::NONE,
        \IntlDateFormatter::FULL,
        \IntlDateFormatter::LONG,
        \IntlDateFormatter::MEDIUM,
        \IntlDateFormatter::SHORT,
    );

    protected $inputTimezone;

    protected $outputTimezone;

    /**
     * @param string $inputTimezone  The name of the input timezone
     * @param string $outputTimezone The name of the output timezone
     *
     * @throws UnexpectedTypeException  if a timezone is not a string
     * @throws InvalidArgumentException if a timezone is not valid
     */
    public function __construct($inputTimezone = null, $outputTimezone = null)
    {
        if (null !== $inputTimezone && !is_string($inputTimezone)) {
            throw new UnexpectedTypeException($inputTimezone, 'string');
        }

        if (null !== $outputTimezone && !is_string($outputTimezone)) {
            throw new UnexpectedTypeException($outputTimezone, 'string');
        }

        $this->inputTimezone = $inputTimezone ?: date_default_timezone_get();
        $this->outputTimezone = $outputTimezone ?: date_default_timezone_get();

        // Check if input and output timezones are valid
        try {
            new \DateTimeZone($this->inputTimezone);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('Input timezone is invalid: %s.', $this->inputTimezone), $e->getCode(), $e);
        }

        try {
            new \DateTimeZone($this->outputTimezone);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('Output timezone is invalid: %s.', $this->outputTimezone), $e->getCode(), $e);
        }
    }
}
