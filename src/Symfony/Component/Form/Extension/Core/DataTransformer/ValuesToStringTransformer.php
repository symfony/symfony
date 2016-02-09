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
 * Converts an array of values to a string with multiple values separated by a delimiter.
 *
 * @author Bilal Amarni <bilal.amarni@gmail.com>
 */
class ValuesToStringTransformer implements DataTransformerInterface
{
    /**
     * @var string
     */
    private $delimiter;

    /**
     * @var bool
     */
    private $trim;

    /**
     * @param string $delimiter
     * @param bool   $trim
     */
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
            return '';
        }

        if (!is_array($array)) {
            throw new TransformationFailedException('Expected an array');
        }

        return implode($this->delimiter, $array);
    }

    /**
     * @param string $string
     *
     * @return array
     *
     * @throws UnexpectedTypeException if the given value is not a string
     */
    public function reverseTransform($string)
    {
        if (empty($string)) {
            return array();
        }

        if (!is_string($string)) {
            throw new TransformationFailedException('Expected a string');
        }

        $values = explode($this->delimiter, $string);

        if ($this->trim) {
            $values = array_map('trim', $values);
        }

        return $values;
    }
}
