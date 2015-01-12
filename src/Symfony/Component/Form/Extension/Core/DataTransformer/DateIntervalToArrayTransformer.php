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
 * Transforms between a normalized date interval and a interval string/array.
 *
 * @author Steffen Roßkamp <steffen.rosskamp@gimmickmedia.de>
 */
class DateIntervalToArrayTransformer implements DataTransformerInterface
{
    private $fields;
    private $availableFields = array(
        'years' => 'y',
        'months' => 'm',
        'days' => 'd',
        'hours' => 'h',
        'minutes' => 'i',
        'seconds' => 's',
        'invert' => 'r',
    );

    /**
     * Constructor.
     *
     * @param array $fields The date fields
     * @param bool  $pad    Whether to use padding
     */
    public function __construct(array $fields = null, $pad = false)
    {
        if (null === $fields) {
            $fields = array('years', 'months', 'days', 'hours', 'minutes', 'seconds', 'invert');
        }
        $this->fields = $fields;
        $this->pad = (bool) $pad;
    }

    /**
     * Transforms a normalized date interval into a interval array.
     *
     * @param  \DateInterval                 $dateInterval Normalized date interval.
     * @return array                         Interval array.
     * @throws TransformationFailedException If the given value is not an
     *                                                    instance of \DateInterval.
     */
    public function transform($dateInterval)
    {
        if (null === $dateInterval) {
            return array_intersect_key(
                array(
                    'years' => '',
                    'months' => '',
                    'weeks' => '',
                    'days' => '',
                    'hours' => '',
                    'minutes' => '',
                    'seconds' => '',
                    'invert' => false,
                ),
                array_flip($this->fields)
            );
        }
        if (!$dateInterval instanceof \DateInterval) {
            throw new TransformationFailedException('Expected a \DateInterval.');
        }
        $result = array();
        foreach ($this->availableFields as $field => $char) {
            $result[$field] = $dateInterval->format('%'.($this->pad ? strtoupper($char) : $char));
        }
        if (in_array('weeks', $this->fields, true)) {
            $result['weeks'] = 0;
            if (isset($result['days']) && (int) $result['days'] >= 7) {
                $result['weeks'] = (string) floor($result['days'] / 7);
                $result['days'] = (string) ($result['days'] % 7);
            }
        }
        $result['invert'] = $result['invert'] === '-' ? true : false;
        $result = array_intersect_key($result, array_flip($this->fields));

        return $result;
    }

    /**
     * Transforms a interval array into a normalized date interval.
     *
     * @param  array                         $value Interval array
     * @return \DateInterval                 Normalized date interval
     * @throws TransformationFailedException If the given value is not an array,
     *                                             if the value could not be transformed.
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return;
        }
        if (!is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }
        if ('' === implode('', $value)) {
            return;
        }
        $emptyFields = array();
        foreach ($this->fields as $field) {
            if (!isset($value[$field])) {
                $emptyFields[] = $field;
            }
        }
        if (count($emptyFields) > 0) {
            throw new TransformationFailedException(
                sprintf(
                    'The fields "%s" should not be empty',
                    implode('", "', $emptyFields)
                )
            );
        }
        if (isset($value['invert']) && !is_bool($value['invert'])) {
            throw new TransformationFailedException('The value of "invert" must be boolean');
        }
        foreach ($this->availableFields as $field => $char) {
            if ($field !== 'invert' && isset($value[$field]) && !ctype_digit((string) $value[$field])) {
                throw new TransformationFailedException(sprintf('This amount of "%s" is invalid', $field));
            }
        }
        try {
            if (!empty($value['weeks'])) {
                $interval = sprintf(
                    'P%sY%sM%sWT%sH%sM%sS',
                    empty($value['years']) ? '0' : $value['years'],
                    empty($value['months']) ? '0' : $value['months'],
                    empty($value['weeks']) ? '0' : $value['weeks'],
                    empty($value['hours']) ? '0' : $value['hours'],
                    empty($value['minutes']) ? '0' : $value['minutes'],
                    empty($value['seconds']) ? '0' : $value['seconds']
                );
            } else {
                $interval = sprintf(
                    'P%sY%sM%sDT%sH%sM%sS',
                    empty($value['years']) ? '0' : $value['years'],
                    empty($value['months']) ? '0' : $value['months'],
                    empty($value['days']) ? '0' : $value['days'],
                    empty($value['hours']) ? '0' : $value['hours'],
                    empty($value['minutes']) ? '0' : $value['minutes'],
                    empty($value['seconds']) ? '0' : $value['seconds']
                );
            }
            $dateInterval = new \DateInterval($interval);
            if (!empty($value['invert'])) {
                $dateInterval->invert = $value['invert'] ? 1 : 0;
            }
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        return $dateInterval;
    }
}
