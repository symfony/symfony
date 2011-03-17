<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MessageDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Clément JOBEILI <clement.jobeili@gmail.com>
 */
class MessageDataCollector extends DataCollector
{
    protected $logger;

    public function __construct(\Swift_Events_SendListener $logger, \Swift_Mailer $mailer)
    {
        $this->logger = $logger;
        // we do nothing with the mailer
        // it's in the constructor just to force SwiftMailer to be initialized
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data['messages'] = $this->logger->getMessages();
        $this->data['messageCount'] = $this->logger->countMessages();
    }

    public function getMessageCount()
    {
        return $this->data['messageCount'];
    }

    public function getMessages()
    {
        return $this->data['messages'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'message';
    }
}
