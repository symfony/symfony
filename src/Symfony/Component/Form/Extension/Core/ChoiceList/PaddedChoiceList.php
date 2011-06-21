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
     * @param array|\Closure $values    The available choices
     * @param integer        $padLength The length to pad the choices
     * @param string         $padString The padding character
     * @param integer        $padType   The direction of padding
     *
     * @throws UnexpectedTypeException if the type of the values parameter is not supported
     */
    public function __construct($values, $padLength, $padString, $padType = STR_PAD_LEFT)
    {
        parent::__construct($values);

        $this->padLength = $padLength;
        $this->padString = $padString;
        $this->padType = $padType;
    }

    /**
     * Initializes the list of choices.
     *
     * Each choices is padded according to the format given in the constructor
     *
     * @throws UnexpectedTypeException if the function does not return an array
     */
    protected function load()
    {
        parent::load();

        foreach ($this->choices as $key => $choice) {
            $this->choices[$key] = str_pad($choice, $this->padLength, $this->padString, $this->padType);
        }
    }
}
