<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList;

use Symfony\Component\Form\Exception\UnexpectedTypeException;

class DefaultChoiceList implements ChoiceListInterface
{
    private $form;

    /**
     * Stores the preferred choices with the choices as keys
     * @var array
     */
    private $preferredChoices = array();

    /**
     * Stores the choices
     * You should only access this property through getChoices()
     * @var array
     */
    private $choices = array();

    private $initialized = false;

    public function __construct($choices, array $preferredChoices = array())
    {
        if (!is_array($choices) && !$choices instanceof \Closure) {
            throw new UnexpectedTypeException($choices, 'array or \Closure');
        }

        $this->choices = $choices;
        $this->preferredChoices = array_flip($preferredChoices);
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel($choice)
    {
        $choices = $this->getChoices();

        return isset($choices[$choice]) ? $choices[$choice] : null;
    }

    /**
     * Returns the choices
     *
     * If the choices were given as a closure, the closure is executed on
     * the first call of this method.
     *
     * @return array
     */
    public function getChoices()
    {
        $this->initializeChoices();

        return $this->choices;
    }

    /**
     * {@inheritDoc}
     */
    public function getOtherChoices()
    {
        return array_diff_key($this->getChoices(), $this->preferredChoices);
    }

    /**
     * {@inheritDoc}
     */
    public function getPreferredChoices()
    {
        return array_intersect_key($this->getChoices(), $this->preferredChoices);
    }

    /**
     * {@inheritDoc}
     */
    public function isChoiceGroup($choice)
    {
        return is_array($choice) || $choice instanceof \Traversable;
    }

    /**
     * {@inheritDoc}
     */
    public function isChoiceSelected($choice, $displayedData)
    {
        return in_array((string) $choice, (array) $displayedData, true);
    }

    /**
     * Initializes the choices
     *
     * If the choices were given as a closure, the closure is executed now.
     *
     * @return array
     */
    protected function initializeChoices()
    {
        if (!$this->initialized) {
            $this->choices = $this->getInitializedChoices($this->choices);
            $this->initialized = true;
        }
    }

    protected function getInitializedChoices($choices)
    {
        if ($choices instanceof \Closure) {
            $choices = $choices->__invoke();

            if (!is_array($choices)) {
                throw new UnexpectedTypeException($choices, 'array');
            }
        }

        return $choices;
    }
}