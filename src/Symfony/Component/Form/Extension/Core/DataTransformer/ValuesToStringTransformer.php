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

use Symfony\Component\Form\Exception\TransformationFailedException;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Converts a string with multiple values separated by a delimiter to an array of values.
 * 
 * @author Bilal Amarni <bilal.amarni@gmail.com>
 */
class ValuesToStringTransformer implements DataTransformerInterface
{
    private $delimiter;
    private $trim;

    public function __construct($delimiter, $trim)
    {
        $this->delimiter = $delimiter;
        $this->trim = $trim;
    }

    /**
     * @param array $array
     *
     * @return string
     *
     * @throws UnexpectedTypeException if the given value is not an array
     */
    public function transform($array)
    {
        if (null === $array) {
            return array();
        }

        if (!is_array($array)) {
            throw new UnexpectedTypeException($array, 'array');
        }

        return implode($this->delimiter, $array);
    }

    /**
     * @param string $string
     *
     * @return array
     *
     * @throws UnexpectedTypeException       if the given value is not a string
     * @throws TransformationFailedException if could not find all matching choices for the given labels
     */
    public function reverseTransform($string)
    {
        if (empty($string)) {
            return array();
        }

        if (!is_string($string)) {
            throw new UnexpectedTypeException($string, 'string');
        }

        $values = explode($this->delimiter, $string);

        if ($this->trim) {
            $values = array_map('trim', $values);
        }

        return $values;
    }
}
