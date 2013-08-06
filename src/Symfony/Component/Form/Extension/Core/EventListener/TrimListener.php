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

/**
 * Trims string data
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TrimListener implements EventSubscriberInterface
{
    public function preSubmit(FormEvent $event)
    {
        $data = $event->getData();

        if (!is_string($data)) {
            return;
        }

        if (null !== $result = @preg_replace('/^[\pZ\p{Cc}]+|[\pZ\p{Cc}]+$/u', '', $data)) {
            $event->setData($result);
        } else {
            $event->setData(trim($data));
        }
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
