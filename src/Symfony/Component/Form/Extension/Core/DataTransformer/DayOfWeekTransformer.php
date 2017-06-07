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
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms a day of week between an iso-8601 integer and a localized string.
 *
 * @author Sebastien Lavoie <seb@wemakecustom.com>
 */
class DayOfWeekTransformer implements DataTransformerInterface
{
    /**
     * @var string
     */
    private $pattern;

    /**
     * @var DateTimeToLocalizedStringTransformer
     */
    private $transformer;

    /**
     * Constructor.
     *
     * @param string $pattern A pattern to pass to \IntlDateFormatter
     */
    public function __construct($pattern = 'eeee')
    {
        if (!preg_match('/^e{1,5}$/i', $pattern)) {
            throw new UnexpectedTypeException($pattern, '/^e{1,5}$/i');
        }

        $this->pattern = $pattern;
        $this->transformer = new DateTimeToLocalizedStringTransformer(
            null,
            null,
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::GREGORIAN,
            $pattern
        );
    }

    /**
     * Transforms a normalized date into a localized date string/array.
     *
     * @param int     $int iso-8601 Day of week in integer (1 = monday, 7 = sunday)
     *
     * @return string Localized string, depending on pattern.
     *
     * @throws TransformationFailedException If the given value is not an integer
     *                                       and a valid day of the week.
     */
    public function transform($value)
    {
        if (!is_numeric($value)) {
            throw new TransformationFailedException('Expected an integer.');
        }

        $value = intval($value);

        if (!in_array($value, range(1, 7))) {
            throw new TransformationFailedException('Expected a day of week [1-7].');
        }

        // cannot use IntlDateFormatter::parse or DateTime::createFromFormat
        // https://github.com/symfony/symfony/pull/13471#issuecomment-72063748
        $datetime = new \DateTime('sunday');
        $datetime->modify('+'.$value.' day');

        return $this->transformer->transform($datetime);
    }

    /**
     * Transforms a localized date string/array into a normalized date.
     *
     * @param string|int     $value Localized string, depending on pattern.
     *
     * @return int     Day of week in integer (1 = monday, 7 = sunday)
     *
     * @throws TransformationFailedException if the given value is not a string
     *                                       or if the date could not be parsed.
     */
    public function reverseTransform($value)
    {
        if ($this->pattern === 'e' || $this->pattern === 'ee') {
            if (!in_array($value, range(1, 7))) {
                throw new TransformationFailedException('Expected a day of week [1-7].');
            }
            $value = ''.$value; // DateTimeToLocalizedStringTransformer expects a string
        } elseif (!is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        $datetime = $this->transformer->reverseTransform($value);

        if (null === $datetime) {
            throw new TransformationFailedException('Unable to reverseTransform \''.$value.'\'.');
        }

        return $datetime->format('N');
    }
}
