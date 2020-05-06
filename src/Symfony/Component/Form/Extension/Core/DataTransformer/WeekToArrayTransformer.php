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
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms between an ISO 8601 week date string and an array.
 *
 * @author Damien Fayet <damienf1521@gmail.com>
 */
class WeekToArrayTransformer implements DataTransformerInterface
{
    /**
     * Transforms a string containing an ISO 8601 week date into an array.
     *
     * @param string|null $value A week date string
     *
     * @return array A value containing year and week
     *
     * @throws TransformationFailedException If the given value is not a string,
     *                                       or if the given value does not follow the right format
     */
    public function transform($value)
    {
        if (null === $value) {
            return ['year' => null, 'week' => null];
        }

        if (!\is_string($value)) {
            throw new TransformationFailedException(sprintf('Value is expected to be a string but was "%s".', get_debug_type($value)));
        }

        if (0 === preg_match('/^(?P<year>\d{4})-W(?P<week>\d{2})$/', $value, $matches)) {
            throw new TransformationFailedException('Given data does not follow the date format "Y-\WW".');
        }

        return [
            'year' => (int) $matches['year'],
            'week' => (int) $matches['week'],
        ];
    }

    /**
     * Transforms an array into a week date string.
     *
     * @param array $value An array containing a year and a week number
     *
     * @return string|null A week date string following the format Y-\WW
     *
     * @throws TransformationFailedException If the given value can not be merged in a valid week date string,
     *                                       or if the obtained week date does not exists
     */
    public function reverseTransform($value)
    {
        if (null === $value || [] === $value) {
            return null;
        }

        if (!\is_array($value)) {
            throw new TransformationFailedException(sprintf('Value is expected to be an array, but was "%s".', get_debug_type($value)));
        }

        if (!\array_key_exists('year', $value)) {
            throw new TransformationFailedException('Key "year" is missing.');
        }

        if (!\array_key_exists('week', $value)) {
            throw new TransformationFailedException('Key "week" is missing.');
        }

        if ($additionalKeys = array_diff(array_keys($value), ['year', 'week'])) {
            throw new TransformationFailedException(sprintf('Expected only keys "year" and "week" to be present, but also got ["%s"].', implode('", "', $additionalKeys)));
        }

        if (null === $value['year'] && null === $value['week']) {
            return null;
        }

        if (!\is_int($value['year'])) {
            throw new TransformationFailedException(sprintf('Year is expected to be an integer, but was "%s".', get_debug_type($value['year'])));
        }

        if (!\is_int($value['week'])) {
            throw new TransformationFailedException(sprintf('Week is expected to be an integer, but was "%s".', get_debug_type($value['week'])));
        }

        // The 28th December is always in the last week of the year
        if (date('W', strtotime('28th December '.$value['year'])) < $value['week']) {
            throw new TransformationFailedException(sprintf('Week "%d" does not exist for year "%d".', $value['week'], $value['year']));
        }

        return sprintf('%d-W%02d', $value['year'], $value['week']);
    }
}
