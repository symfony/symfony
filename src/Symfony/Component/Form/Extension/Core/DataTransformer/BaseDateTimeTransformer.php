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

abstract class BaseDateTimeTransformer implements DataTransformerInterface
{
    protected static $formats = [
        \IntlDateFormatter::NONE,
        \IntlDateFormatter::FULL,
        \IntlDateFormatter::LONG,
        \IntlDateFormatter::MEDIUM,
        \IntlDateFormatter::SHORT,
    ];

    protected $inputTimezone;

    protected $outputTimezone;

    /**
     * @param string|null $inputTimezone  The name of the input timezone
     * @param string|null $outputTimezone The name of the output timezone
     *
     * @throws InvalidArgumentException if a timezone is not valid
     */
    public function __construct(string $inputTimezone = null, string $outputTimezone = null)
    {
        $this->inputTimezone = $inputTimezone ?: date_default_timezone_get();
        $this->outputTimezone = $outputTimezone ?: date_default_timezone_get();

        // Check if input and output timezones are valid
        try {
            new \DateTimeZone($this->inputTimezone);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('Input timezone is invalid: "%s".', $this->inputTimezone), $e->getCode(), $e);
        }

        try {
            new \DateTimeZone($this->outputTimezone);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('Output timezone is invalid: "%s".', $this->outputTimezone), $e->getCode(), $e);
        }
    }
}
