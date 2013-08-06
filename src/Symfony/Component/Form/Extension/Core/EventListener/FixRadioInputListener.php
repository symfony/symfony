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

use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface;

/**
 * Takes care of converting the input from a single radio button
 * to an array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FixRadioInputListener implements EventSubscriberInterface
{
    private $choiceList;

    private $placeholderPresent;

    /**
     * Constructor.
     *
     * @param ChoiceListInterface $choiceList
     * @param Boolean             $placeholderPresent
     */
    public function __construct(ChoiceListInterface $choiceList, $placeholderPresent)
    {
        $this->choiceList = $choiceList;
        $this->placeholderPresent = $placeholderPresent;
    }

    public function preSubmit(FormEvent $event)
    {
        $value = $event->getData();
        $index = current($this->choiceList->getIndicesForValues(array($value)));

        $event->setData(false !== $index ? array($index => $value) : ($this->placeholderPresent ? array('placeholder' => '') : array()))   ;
    }

    /**
     * Alias of {@link preSubmit()}.
     *
     * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
     *             {@link preSubmit()} instead.
     */
    public function preBind(FormEvent $event)
    {
        $this->preSubmit($event);
    }

    public static function getSubscribedEvents()
    {
        return array(FormEvents::PRE_SUBMIT => 'preSubmit');
    }
}
