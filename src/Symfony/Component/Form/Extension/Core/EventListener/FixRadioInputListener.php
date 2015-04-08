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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Takes care of converting the input from a single radio button
 * to an array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.7, to be removed in 3.0.
 *             Use {@link \Symfony\Component\Form\Extension\Core\DataMapper\RadioListMapper} instead.
 */
class FixRadioInputListener implements EventSubscriberInterface
{
    private $choiceList;

    private $placeholderPresent;

    /**
     * Constructor.
     *
     * @param ChoiceListInterface $choiceList
     * @param bool                $placeholderPresent
     */
    public function __construct(ChoiceListInterface $choiceList, $placeholderPresent)
    {
        $this->choiceList = $choiceList;
        $this->placeholderPresent = $placeholderPresent;

        trigger_error('The class '.__CLASS__.' is deprecated since version 2.7 and will be removed in 3.0. Use Symfony\Component\Form\Extension\Core\DataMapper\RadioListMapper instead.', E_USER_DEPRECATED);
    }

    public function preSubmit(FormEvent $event)
    {
        $data = $event->getData();

        // Since expanded choice fields are completely loaded anyway, we
        // can just as well get the values again without losing performance.
        $existingValues = $this->choiceList->getValues();

        if (false !== ($index = array_search($data, $existingValues, true))) {
            $data = array($index => $data);
        } elseif ('' === $data || null === $data) {
            // Empty values are always accepted.
            $data = $this->placeholderPresent ? array('placeholder' => '') : array();
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
