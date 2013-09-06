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
 * Takes care of converting the input from a list of checkboxes to a correctly
 * indexed array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
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
        $values = (array) $event->getData();
        $indices = $this->choiceList->getIndicesForValues($values);

        $event->setData(count($indices) > 0 ? array_combine($indices, $values) : array());
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
