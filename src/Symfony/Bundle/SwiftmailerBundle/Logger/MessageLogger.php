<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\Logger;

use Symfony\Component\EventDispatcher\Event;

/**
 * MessageLogger.
 *
 * @author Clément JOBEILI <clement.jobeili@gmail.com>
 */
class MessageLogger implements \Swift_Events_SendListener
{

    /**
     * @var array
     */
    protected $messages;

    public function __construct()
    {
        $this->messages = array();   
    }

    /**
     * Get the message list
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * Get the message count
     *
     * @return int count
     */
    public function countMessages()
    {
        return count($this->messages);
    }
    
    /**
     * Empty the message list
     * 
     */
    public function clear()
    {
        $this->messages = array();
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSendPerformed(\Swift_Events_SendEvent $evt)
    {
        $this->messages[] = $message = clone $evt->getMessage();
    }

    /**
     * {@inheritdoc}
     */
    public function sendPerformed(\Swift_Events_SendEvent $evt)
    {
    }
}