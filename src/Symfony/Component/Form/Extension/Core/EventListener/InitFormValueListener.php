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
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Set an initial value on a form field that does not have an underlying object bound.
 *
 * @author Ross Phillipson <rosco404@gmail.com>
 */
class InitFormValueListener implements EventSubscriberInterface
{
    public function onPreSetData(FormEvent $event): void
    {
        $data = $event->getData() ?? $event->getForm()->getConfig()->getOption('init_value');

        $event->setData($data);
    }

    public static function getSubscribedEvents(): array
    {
        return [FormEvents::PRE_SET_DATA => 'onPreSetData'];
    }
}