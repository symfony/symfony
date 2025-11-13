<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Bridge\Sendgrid\Header\SuppressionGroupHeader;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Kevin Verschaeve
 */
class SendgridSmtpTransport extends EsmtpTransport
{
    public function __construct(#[\SensitiveParameter] string $key, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null, private ?string $region = null)
    {
        $host = 'smtp.sendgrid.net';

        if (null !== $region && 'global' !== $region) {
            $host = \sprintf('smtp.%s.sendgrid.net', $region);
        }

        parent::__construct($host, 465, true, $dispatcher, $logger);

        $this->setUsername('apikey');
        $this->setPassword($key);
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $this->addSendgridHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addSendgridHeaders(Message $message): void
    {
        $headers = $message->getHeaders();

        if ($headers->has('X-SMTPAPI')) {
            return;
        }

        foreach ($headers->all() as $header) {
            if ($header instanceof SuppressionGroupHeader) {
                break;
            }
        }

        if (!$header instanceof SuppressionGroupHeader) {
            return;
        }

        $payload = [
            'asm' => [
                'group_id' => $header->getGroupId(),
            ],
        ];

        if ($groupsToDisplay = $header->getGroupsToDisplay()) {
            $payload['asm']['groups_to_display'] = $groupsToDisplay;
        }

        $headers->addTextHeader('X-SMTPAPI', json_encode($payload, \JSON_UNESCAPED_SLASHES));
        $headers->remove('X-Sendgrid-SuppressionGroup');
    }
}
