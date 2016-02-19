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

/**
 * Maps choices to/from radio forms.
 *
 * A {@link ChoiceListInterface} implementation is used to find the
 * corresponding string values for the choices. The radio form whose "value"
 * option corresponds to the selected value is marked as selected.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RadioListMapper implements DataMapperInterface
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
    public function mapDataToForms($data, $radios)
    {
        foreach ($radios as $radio) {
            $value = $radio->getConfig()->getOption('value');
            $radio->setData($value === $data ? true : false);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mapFormsToData($radios, &$choice)
    {
        $choice = null;

        foreach ($radios as $radio) {
            if ($radio->getData()) {
                if ('placeholder' === $radio->getName()) {
                    return;
                }

                $value = $radio->getConfig()->getOption('value');
                $choice = current($this->choiceList->getChoicesForValues(array($value)));

                return;
            }
        }
    }
}
