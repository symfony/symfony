<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Infobip\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @see https://www.infobip.com/docs/api#channels/email/send-email
 */
final class InfobipApiTransport extends AbstractApiTransport
{
    private const API_VERSION = '3';

    private const HEADER_TO_MESSAGE = [
        'X-Infobip-IntermediateReport' => 'intermediateReport',
        'X-Infobip-NotifyUrl' => 'notifyUrl',
        'X-Infobip-NotifyContentType' => 'notifyContentType',
        'X-Infobip-MessageId' => 'messageId',
    ];

    private string $key;

    public function __construct(string $key, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('infobip+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $formData = $this->formDataPart($email, $envelope);

        $headers = $formData->getPreparedHeaders()->toArray();
        $headers[] = 'Authorization: App '.$this->key;
        $headers[] = 'Accept: application/json';

        $response = $this->client->request(
            'POST',
            sprintf('https://%s/email/%s/send', $this->getEndpoint(), self::API_VERSION),
            [
                'headers' => $headers,
                'body' => $formData->bodyToIterable(),
            ]
        );

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Infobip server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException(sprintf('Unable to send an email: "%s" (code %d).', $response->getContent(false), $statusCode), $response);
        }

        try {
            $result = $response->toArray();
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException(sprintf('Unable to send an email: "%s" (code %d).', $response->getContent(false), $statusCode), $response, 0, $e);
        }

        if (isset($result['messages'][0]['messageId'])) {
            $sentMessage->setMessageId($result['messages'][0]['messageId']);
        }

        return $response;
    }

    private function getEndpoint(): ?string
    {
        return $this->host.($this->port ? ':'.$this->port : '');
    }

    private function formDataPart(Email $email, Envelope $envelope): FormDataPart
    {
        $fields = [
            'from' => $envelope->getSender()->toString(),
            'subject' => $email->getSubject(),
        ];

        $this->addressesFormData($fields, 'to', $this->getRecipients($email, $envelope));

        if ($email->getCc()) {
            $this->addressesFormData($fields, 'cc', $email->getCc());
        }

        if ($email->getBcc()) {
            $this->addressesFormData($fields, 'bcc', $email->getBcc());
        }

        if ($email->getReplyTo()) {
            $this->addressesFormData($fields, 'replyto', $email->getReplyTo());
        }

        if ($email->getTextBody()) {
            $fields['text'] = $email->getTextBody();
        }

        if ($email->getHtmlBody()) {
            $fields['HTML'] = $email->getHtmlBody();
        }

        $this->attachmentsFormData($fields, $email);

        foreach ($email->getHeaders()->all() as $header) {
            if ($convertConf = self::HEADER_TO_MESSAGE[$header->getName()] ?? false) {
                $fields[$convertConf] = $header->getBodyAsString();
            }
        }

        return new FormDataPart($fields);
    }

    private function attachmentsFormData(array &$message, Email $email): void
    {
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $dataPart = new DataPart($attachment->getBody(), $filename, $attachment->getMediaType().'/'.$attachment->getMediaSubtype());

            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                $message[] = ['inlineImage' => $dataPart];
            } else {
                $message[] = ['attachment' => $dataPart];
            }
        }
    }

    /**
     * @param Address[] $addresses
     */
    private function addressesFormData(array &$message, string $property, array $addresses): void
    {
        foreach ($addresses as $address) {
            $message[] = [$property => $address->toString()];
        }
    }
}
