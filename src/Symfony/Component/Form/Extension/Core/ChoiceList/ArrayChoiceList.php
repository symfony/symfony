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

    public function __construct($choices)
    {
        if (!(is_array($choices) || (!is_string($choices) && is_callable($choices)))) {
            throw new UnexpectedTypeException($choices, 'array or function');
        }

        $this->choices = $choices;
    }

    public function getChoices()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->choices;
    }

    /**
     * @see Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface::getChoices
     */
    protected function load()
    {
        $this->loaded = true;

        if (is_callable($this->choices)) {
            $this->choices = call_user_func($this->choices);

            if (!is_array($this->choices)) {
                throw new UnexpectedTypeException($this->choices, 'array');
            }
        }
    }
}