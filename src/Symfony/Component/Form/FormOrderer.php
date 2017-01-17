<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\InvalidConfigurationException;

/**
 * Form orderer.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class FormOrderer
{
    private $weights;
    private $deferred;
    private $firstWeight;
    private $currentWeight;
    private $lastWeight;

    /**
     * Orders the form.
     *
     * @param FormInterface $form
     *
     * @return array The ordered form child names
     *
     * @throws InvalidConfigurationException If a position is not valid
     */
    public function order(FormInterface $form)
    {
        $this->reset();

        foreach ($form as $child) {
            $position = $child->getConfig()->getPosition();

            if (empty($position)) {
                $this->processEmptyPosition($child);
            } elseif (is_string($position)) {
                $this->processStringPosition($child, $position);
            } else {
                $this->processArrayPosition($child, $position);
            }
        }

        asort($this->weights, SORT_NUMERIC);

        return array_keys($this->weights);
    }

    /**
     * Process the form using the current weight in order to maintain the default order
     *
     * @param FormInterface $form
     */
    private function processEmptyPosition(FormInterface $form)
    {
        $this->processWeight($form, $this->currentWeight);
    }

    /**
     * Process the form using the current first/last weight in order to put your form at the
     * first/last position according to the default order
     *
     * @param FormInterface $form
     * @param string        $position
     */
    private function processStringPosition(FormInterface $form, $position)
    {
        if ($position === 'first') {
            $this->processFirst($form);
        } else {
            $this->processLast($form);
        }
    }

    /**
     * Process the form using the weight of the "before" or "after" form
     * If the "before" or "after" form has not been processed yet, we defer it for the next forms
     *
     * @param FormInterface $form
     * @param array         $position
     */
    private function processArrayPosition(FormInterface $form, array $position)
    {
        if (isset($position['before'])) {
            $this->processBefore($form, $position['before']);
        }

        if (isset($position['after'])) {
            $this->processAfter($form, $position['after']);
        }
    }

    /**
     * Process the form using the current first weight in order to put
     * your form at the first position according to the default order
     *
     * @param FormInterface $form
     */
    private function processFirst(FormInterface $form)
    {
        $this->processWeight($form, $this->firstWeight++);
    }

    /**
     * Processes the form using the current last weight in order to put
     * your form at the last position according to the default order
     *
     * @param FormInterface $form
     */
    private function processLast(FormInterface $form)
    {
        $this->processWeight($form, $this->lastWeight + 1);
    }

    /**
     * Process the form using the weight of the "before" form
     * If the "before" form has not been processed yet, we defer it for the next forms
     *
     * @param FormInterface $form
     * @param string        $before
     */
    private function processBefore(FormInterface $form, $before)
    {
        if (!isset($this->weights[$before])) {
            $this->processDeferred($form, $before, 'before');
        } else {
            $this->processWeight($form, $this->weights[$before]);
        }
    }

    /**
     * Process the form using the weight of the "after" form
     * If the "after" form has not been processed yet, we defer it for the next forms
     *
     * @param FormInterface $form
     * @param string        $after
     */
    private function processAfter(FormInterface $form, $after)
    {
        if (!isset($this->weights[$after])) {
            $this->processDeferred($form, $after, 'after');
        } else {
            $this->processWeight($form, $this->weights[$after] + 1);
        }
    }

    /**
     * Process the form using the given weight
     *
     * This method also updates the orderer state accordingly
     *
     * @param FormInterface $form
     * @param int           $weight
     */
    private function processWeight(FormInterface $form, $weight)
    {
        foreach ($this->weights as &$weightRef) {
            if ($weightRef >= $weight) {
                ++$weightRef;
            }
        }

        if ($this->currentWeight >= $weight) {
            ++$this->currentWeight;
        }

        ++$this->lastWeight;

        $this->weights[$form->getName()] = $weight;
        $this->finishWeight($form, $weight);
    }

    /**
     * Finishes the form weight processing by trying to process deferred forms
     * which refers to the current processed form
     *
     * @param FormInterface $form
     * @param int           $weight
     * @param string        $position
     *
     * @return int The new weight
     */
    private function finishWeight(FormInterface $form, $weight, $position = null)
    {
        if ($position === null) {
            foreach (array_keys($this->deferred) as $position) {
                $weight = $this->finishWeight($form, $weight, $position);
            }
        } else {
            $name = $form->getName();

            if (isset($this->deferred[$position][$name])) {
                $postIncrement = $position === 'before';

                foreach ($this->deferred[$position][$name] as $deferred) {
                    $this->processWeight($deferred, $postIncrement ? $weight++ : ++$weight);
                }

                unset($this->deferred[$position][$name]);
            }
        }

        return $weight;
    }

    /**
     * Processes a deferred form by checking if it is valid and
     * if it does not become a circular or symmetric ordering
     *
     * @param FormInterface $form
     * @param string        $deferred
     * @param string        $position
     *
     * @throws InvalidConfigurationException If the deferred form does not exist
     */
    private function processDeferred(FormInterface $form, $deferred, $position)
    {
        if (!$form->getParent()->has($deferred)) {
            throw new InvalidConfigurationException(sprintf('The "%s" form is configured to be placed just %s the form "%s" but the form "%s" does not exist.', $form->getName(), $position, $deferred, $deferred));
        }

        $this->deferred[$position][$deferred][] = $form;

        $name = $form->getName();

        $this->detectCircularDeferred($name, $position);
        $this->detectedSymmetricDeferred($name, $deferred, $position);
    }

    /**
     * Detects circular deferred forms for after/before position such as A => B => C => A
     *
     * @param string $name
     * @param string $position
     * @param array  $stack
     *
     * @throws InvalidConfigurationException If there is a circular before/after deferred
     */
    private function detectCircularDeferred($name, $position, array $stack = array())
    {
        if (!isset($this->deferred[$position][$name])) {
            return;
        }

        $stack[] = $name;

        foreach ($this->deferred[$position][$name] as $deferred) {
            $deferredName = $deferred->getName();

            if ($deferredName === $stack[0]) {
                $stack[] = $stack[0];

                throw new InvalidConfigurationException(sprintf('The form ordering cannot be resolved due to conflict in %s positions (%s).', $position, implode(' => ', $stack)));
            }

            $this->detectCircularDeferred($deferredName, $position, $stack);
        }
    }

    /**
     * Detects symmetric before/after deferred such as A after B and B after A
     *
     * @param string $name
     * @param string $deferred
     * @param string $position
     *
     * @throws InvalidConfigurationException If there is a symetric before/after deferred
     */
    private function detectedSymmetricDeferred($name, $deferred, $position)
    {
        $reversePosition = ($position === 'before') ? 'after' : 'before';

        if (isset($this->deferred[$reversePosition][$name])) {
            foreach ($this->deferred[$reversePosition][$name] as $diff) {
                if ($diff->getName() === $deferred) {
                    throw new InvalidConfigurationException(sprintf('The form ordering does not support symmetrical before/after option (%s <=> %s).', $name, $deferred));
                }
            }
        }
    }

    /**
     * Resets the orderer
     */
    private function reset()
    {
        $this->weights = array();
        $this->deferred = array(
            'before' => array(),
            'after' => array(),
        );

        $this->firstWeight = 0;
        $this->currentWeight = 0;
        $this->lastWeight = 0;
    }
}
