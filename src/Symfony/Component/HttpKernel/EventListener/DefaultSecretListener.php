<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Checks that the kernel.secret parameter isn't set to the default.
 *
 * @author Lee McDermott <github@leemcdermott.co.uk>
 */
class DefaultSecretListener implements EventSubscriberInterface
{
    private $logger;
    private $secret;

    public function __construct(LoggerInterface $logger = null, $secret)
    {
        $this->logger = $logger;
        $this->secret = $secret;
    }

    public function onKernelRequest()
    {
        if ('ThisTokenIsNotSoSecretChangeIt' === $this->secret) {
            $this->logger->alert('The "kernel.secret" parameter is currently set to the default. It is important that you change it');
        }
    }

    public static function getSubscribedEvents()
    {
        return array(KernelEvents::REQUEST => 'onKernelRequest');
    }
}
