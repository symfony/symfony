<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Core\DataMapper;

use Symphony\Component\Form\DataMapperInterface;
use Symphony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Maps choices to/from checkbox forms.
 *
 * A {@link ChoiceListInterface} implementation is used to find the
 * corresponding string values for the choices. Each checkbox form whose "value"
 * option corresponds to any of the selected values is marked as selected.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CheckboxListMapper implements DataMapperInterface
{
    /**
     * {@inheritdoc}
     */
    public function mapDataToForms($choices, $checkboxes)
    {
        if (null === $choices) {
            $choices = array();
        }

        if (!is_array($choices)) {
            throw new UnexpectedTypeException($choices, 'array');
        }

        foreach ($checkboxes as $checkbox) {
            $value = $checkbox->getConfig()->getOption('value');
            $checkbox->setData(in_array($value, $choices, true));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mapFormsToData($checkboxes, &$choices)
    {
        if (!is_array($choices)) {
            throw new UnexpectedTypeException($choices, 'array');
        }

        $values = array();

        foreach ($checkboxes as $checkbox) {
            if ($checkbox->getData()) {
                // construct an array of choice values
                $values[] = $checkbox->getConfig()->getOption('value');
            }
        }

        $choices = $values;
    }
}
