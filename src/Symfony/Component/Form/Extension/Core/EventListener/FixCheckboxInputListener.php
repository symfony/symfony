<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\EventListener;

trigger_error('The class '.__NAMESPACE__.'\FixCheckboxInputListener is deprecated since version 2.7 and will be removed in 3.0. Use Symfony\Component\Form\Extension\Core\DataMapper\CheckboxListMapper instead.', E_USER_DEPRECATED);

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Takes care of converting the input from a list of checkboxes to a correctly
 * indexed array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.7, to be removed in 3.0.
 *             Use {@link \Symfony\Component\Form\Extension\Core\DataMapper\CheckboxListMapper} instead.
 */
class FixCheckboxInputListener implements EventSubscriberInterface
{
    private $choiceList;

    /**
     * Constructor.
     *
     * @param ChoiceListInterface $choiceList
     */
    public function __construct(ChoiceListInterface $choiceList)
    {
        $this->choiceList = $choiceList;
    }

    public function preSubmit(FormEvent $event)
    {
        $data = $event->getData();

        if (is_array($data)) {
            // Flip the submitted values for faster lookup
            // It's better to flip this array than $existingValues because
            // $submittedValues is generally smaller.
            $submittedValues = array_flip($data);

            // Since expanded choice fields are completely loaded anyway, we
            // can just as well get the values again without losing performance.
            $existingValues = $this->choiceList->getValues();

            // Clear the data array and fill it with correct indices
            $data = array();

            foreach ($existingValues as $index => $value) {
                if (isset($submittedValues[$value])) {
                    // Value was submitted
                    $data[$index] = $value;
                    unset($submittedValues[$value]);
                }
            }

            if (count($submittedValues) > 0) {
                throw new TransformationFailedException(sprintf(
                    'The following choices were not found: "%s"',
                    implode('", "', array_keys($submittedValues))
                ));
            }
        } elseif ('' === $data || null === $data) {
            // Empty values are always accepted.
            $data = array();
        }

        // Else leave the data unchanged to provoke an error during submission

        $event->setData($data);
    }

    /**
     * Alias of {@link preSubmit()}.
     *
     * @deprecated since version 2.3, to be removed in 3.0.
     *             Use {@link preSubmit()} instead.
     */
    public function preBind(FormEvent $event)
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.3 and will be removed in 3.0. Use the preSubmit() method instead.', E_USER_DEPRECATED);

        $this->preSubmit($event);
    }

    public static function getSubscribedEvents()
    {
        return array(FormEvents::PRE_SUBMIT => 'preSubmit');
    }
}
