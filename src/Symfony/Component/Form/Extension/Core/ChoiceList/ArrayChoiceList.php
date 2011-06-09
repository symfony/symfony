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

use Symfony\Component\Form\Exception\UnexpectedTypeException;

class ArrayChoiceList implements ChoiceListInterface
{
    protected $choices;

    protected $loaded = false;

    /**
     * Constructor.
     *
     * @param array|\Closure $choices An array of choices or a function returning an array
     *
     * @throws UnexpectedTypeException if the type of the choices parameter is not supported
     */
    public function __construct($choices)
    {
        if (!is_array($choices) && !$choices instanceof \Closure) {
            throw new UnexpectedTypeException($choices, 'array or \Closure');
        }

        $this->choices = $choices;
    }

    /**
     * Returns a list of choices
     *
     * @return array
     */
    public function getChoices()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->choices;
    }

    /**
     * Initializes the list of choices.
     *
     * @throws UnexpectedTypeException if the function does not return an array
     */
    protected function load()
    {
        $this->loaded = true;

        if ($this->choices instanceof \Closure) {
            $this->choices = call_user_func($this->choices);

            if (!is_array($this->choices)) {
                throw new UnexpectedTypeException($this->choices, 'array');
            }
        }
    }
}
