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

use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Stash a collection prototype during bind.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class PrototypeStashListener implements EventSubscriberInterface
{
    private $stash;

    public function __construct()
    {
        $this->stash = new \SplObjectStorage();
    }

    public function preBind(DataEvent $event)
    {
        $form = $event->getForm();
        $this->stash[$form] = $form['$$name$$'];
        unset($form['$$name$$']);
    }

    public function postBind(DataEvent $event)
    {
        $form = $event->getForm();
        $form['$$name$$'] = $this->stash[$form];
        unset($this->stash[$form]);
    }

    public static function getSubscribedEvents()
    {
        return array(Events::preBind, Events::postBind);
    }
}
