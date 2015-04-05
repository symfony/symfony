<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataMapper;

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception;
use Symfony\Component\Form\Exception\TransformationFailedException;

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
     * @var ChoiceListInterface
     */
    private $choiceList;

    public function __construct(ChoiceListInterface $choiceList)
    {
        $this->choiceList = $choiceList;
    }

    /**
     * {@inheritdoc}
     */
    public function mapDataToForms($choices, $checkboxes)
    {
        if (null === $choices) {
            $choices = array();
        }

        if (!is_array($choices)) {
            throw new TransformationFailedException('Expected an array.');
        }

        try {
            $valueMap = array_flip($this->choiceList->getValuesForChoices($choices));
        } catch (\Exception $e) {
            throw new TransformationFailedException(
                'Can not read the choices from the choice list.',
                $e->getCode(),
                $e
            );
        }

        foreach ($checkboxes as $checkbox) {
            $value = $checkbox->getConfig()->getOption('value');
            $checkbox->setData(isset($valueMap[$value]) ? true : false);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mapFormsToData($checkboxes, &$choices)
    {
        $values = array();

        foreach ($checkboxes as $checkbox) {
            if ($checkbox->getData()) {
                // construct an array of choice values
                $values[] = $checkbox->getConfig()->getOption('value');
            }
        }

        try {
            $choices = $this->choiceList->getChoicesForValues($values);
        } catch (\Exception $e) {
            throw new TransformationFailedException(
                'Can not read the values from the choice list.',
                $e->getCode(),
                $e
            );
        }
    }
}
