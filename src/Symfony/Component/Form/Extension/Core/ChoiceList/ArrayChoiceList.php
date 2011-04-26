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
        if (!is_array($choices) && !$choices instanceof \Closure) {
            throw new UnexpectedTypeException($choices, 'array or \Closure');
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

        if ($this->choices instanceof \Closure) {
            $this->choices = $this->choices->__invoke();

            if (!is_array($this->choices)) {
                throw new UnexpectedTypeException($this->choices, 'array');
            }
        }
    }
}