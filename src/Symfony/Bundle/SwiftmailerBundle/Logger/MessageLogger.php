<?php

namespace Symfony\Bundle\SwiftmailerBundle\Logger;

use Symfony\Component\EventDispatcher\Event;

class MessageLogger implements \Swift_Events_SendListener
{

    protected $messages;

    public function __construct()
    {
        $this->messages = array();   
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function countMessages()
    {
        return count($this->messages);
    }
    public function clear()
    {
        $this->messages = array();
    }

    public function beforeSendPerformed(\Swift_Events_SendEvent $evt)
    {
        $this->messages[] = $message = clone $evt->getMessage();
    }

    public function sendPerformed(\Swift_Events_SendEvent $evt)
    {
    }
}