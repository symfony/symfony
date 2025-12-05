<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Messenger;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\UnsupportedFeatureException;
use Symfony\Component\Mailer\Mime\ProviderTemplatedEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\ProviderTemplatedTransportInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MessageHandler
{
    public function __construct(
        private TransportInterface $transport,
    ) {
    }

    public function __invoke(SendEmailMessage $message): ?SentMessage
    {
        $email = $message->getMessage();
        if ($email instanceof ProviderTemplatedEmail && !$this->transport instanceof ProviderTemplatedTransportInterface) {
            throw new UnsupportedFeatureException();
        }

        return $this->transport->send($message->getMessage(), $message->getEnvelope());
    }
}
