<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\ChoiceList;

class PaddedChoiceList extends ArrayChoiceList
{
    private $padLength;

    private $padString;

    private $padType;

    /**
     * Generates an array of choices for the given values
     *
     * If the values are shorter than $padLength characters, they are padded with
     * zeros on the left side.
     *
     * @param array   $values    The available choices
     * @param integer $padLength The length to pad the choices
     * @param string  $padString The padding character
     * @param integer $padType   The direction of padding
     */
    public function __construct($values, $padLength, $padString, $padType = STR_PAD_LEFT)
    {
        parent::__construct($values);

        $this->padLength = $padLength;
        $this->padString = $padString;
        $this->padType = $padType;
    }

    protected function load()
    {
        parent::load();

        $choices = $this->choices;
        $this->choices = array();

        foreach ($choices as $value) {
            $this->choices[$value] = str_pad($value, $this->padLength, $this->padString, $this->padType);
        }
    }
}