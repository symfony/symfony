<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Swiftmailer\DataCollector;

@trigger_error('The '.__NAMESPACE__.'\MessageDataCollector class is deprecated since version 2.4 and will be removed in 3.0. Use the Symfony\Bundle\SwiftmailerBundle\DataCollector\MessageDataCollector class from SwiftmailerBundle instead. Require symfony/swiftmailer-bundle package to download SwiftmailerBundle with Composer.', E_USER_DEPRECATED);

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MessageDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Clément JOBEILI <clement.jobeili@gmail.com>
 *
 * @deprecated since version 2.4, to be removed in 3.0.
 *             Use the MessageDataCollector from SwiftmailerBundle instead.
 */
class MessageDataCollector extends DataCollector
{
    private $container;
    private $isSpool;

    /**
     * Constructor.
     *
     * We don't inject the message logger and mailer here
     * to avoid the creation of these objects when no emails are sent.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     * @param bool               $isSpool
     */
    public function __construct(ContainerInterface $container, $isSpool)
    {
        $this->container = $container;
        $this->isSpool = $isSpool;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // only collect when Swiftmailer has already been initialized
        if (class_exists('Swift_Mailer', false)) {
            $logger = $this->container->get('swiftmailer.plugin.messagelogger');
            $this->data['messages'] = $logger->getMessages();
            $this->data['messageCount'] = $logger->countMessages();
        } else {
            $this->data['messages'] = array();
            $this->data['messageCount'] = 0;
        }

        $this->data['isSpool'] = $this->isSpool;
    }

    public function getMessageCount()
    {
        return $this->data['messageCount'];
    }

    public function getMessages()
    {
        return $this->data['messages'];
    }

    public function isSpool()
    {
        return $this->data['isSpool'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'swiftmailer';
    }
}
