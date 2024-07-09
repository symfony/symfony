<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Azure\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AzureApiTransport extends AbstractApiTransport
{
    private const HOST = '%s.communication.azure.com';

    /**
     * @param string $key             User Access Key from Azure Communication Service (Primary or Secondary key)
     * @param string $resourceName    The endpoint API URL to which to POST emails to Azure https://{acsResourceName}.communication.azure.com/
     * @param bool   $disableTracking Indicates whether user engagement tracking should be disabled
     * @param string $apiVersion      The version of API to invoke
     */
    public function __construct(
        #[\SensitiveParameter] private string $key,
        private string $resourceName,
        private bool $disableTracking = false,
        private string $apiVersion = '2023-03-31',
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        if (str_ends_with($resourceName, '.')) {
            throw new \Exception('Resource name must not end with a dot ".".');
        }

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('azure+api://%s', $this->getAzureCSEndpoint());
    }

    /**
     * Queues an email message to be sent to one or more recipients.
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $endpoint = $this->getAzureCSEndpoint().'/emails:send?api-version='.$this->apiVersion;
        $payload = $this->getPayload($email, $envelope);

        $response = $this->client->request('POST', 'https://'.$endpoint, [
            'body' => json_encode($payload),
            'headers' => $this->getSignedHeaders($payload, $email),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Azure server.', $response, 0, $e);
        }

        if (202 !== $statusCode) {
            try {
                $result = $response->toArray(false);
                throw new HttpTransportException('Unable to send an email (.'.$result['error']['code'].'): '.$result['error']['message'], $response, $statusCode);
            } catch (DecodingExceptionInterface $e) {
                throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response, 0, $e);
            }
        }

        $sentMessage->setMessageId(json_decode($response->getContent(false), true)['id']);

        return $response;
    }

    /**
     * Get the message request body.
     */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $addressStringifier = function (Address $address) {
            $stringified = ['address' => $address->getAddress()];

            if ($address->getName()) {
                $stringified['displayName'] = $address->getName();
            }

            return $stringified;
        };

        $data = [
            'content' => [
                'html' => $email->getHtmlBody(),
                'plainText' => $email->getTextBody(),
                'subject' => $email->getSubject(),
            ],
            'recipients' => [
                'to' => array_map($addressStringifier, $this->getRecipients($email, $envelope)),
            ],
            'senderAddress' => $envelope->getSender()->getAddress(),
            'attachments' => $this->getMessageAttachments($email),
            'userEngagementTrackingDisabled' => $this->disableTracking,
            'headers' => ($headers = $this->getMessageCustomHeaders($email)) ? $headers : null,
            'importance' => $this->getPriorityLevel($email->getPriority()),
        ];

        if ($emails = array_map($addressStringifier, $email->getCc())) {
            $data['recipients']['cc'] = $emails;
        }

        if ($emails = array_map($addressStringifier, $email->getBcc())) {
            $data['recipients']['bcc'] = $emails;
        }

        if ($emails = array_map($addressStringifier, $email->getReplyTo())) {
            $data['replyTo'] = $emails;
        }

        return $data;
    }

    /**
     * List of attachments. Please note that the service limits the total size
     * of an email request (which includes attachments) to 10 MB.
     */
    private function getMessageAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $disposition = $headers->getHeaderBody('Content-Disposition');

            $att = [
                'name' => $filename,
                'contentInBase64' => base64_encode($attachment->getBody()),
                'contentType' => $headers->get('Content-Type')->getBody(),
            ];

            if ('inline' === $disposition) {
                $att['content_id'] = $filename;
            }

            $attachments[] = $att;
        }

        return $attachments;
    }

    /**
     * The communication domain host, for example my-acs-resource-name.communication.azure.com.
     */
    private function getAzureCSEndpoint(): string
    {
        return $this->host ?: \sprintf(self::HOST, $this->resourceName);
    }

    private function generateContentHash(string $content): string
    {
        return base64_encode(hash('sha256', $content, true));
    }

    /**
     * Generate sha256 hash and encode to base64 to produces the digest string.
     */
    private function generateAuthenticationSignature(string $content): string
    {
        $key = base64_decode($this->key);
        $hashedBytes = hash_hmac('sha256', mb_convert_encoding($content, 'UTF-8'), $key, true);

        return base64_encode($hashedBytes);
    }

    /**
     * Get authenticated headers for signed request,.
     */
    private function getSignedHeaders(array $payload, Email $message): array
    {
        // HTTP Method verb (uppercase)
        $verb = 'POST';

        // Request time
        $datetime = new \DateTime('now', new \DateTimeZone('UTC'));
        $utcNow = $datetime->format('D, d M Y H:i:s \G\M\T');

        // Content hash signature
        $contentHash = $this->generateContentHash(json_encode($payload));

        // ACS Endpoint
        $host = str_replace('https://', '', $this->getAzureCSEndpoint());

        // Sendmail endpoint from communication email delivery service
        $urlPathAndQuery = '/emails:send?api-version='.$this->apiVersion;

        // Signed request headers
        $stringToSign = "{$verb}\n{$urlPathAndQuery}\n{$utcNow};{$host};{$contentHash}";

        // Authenticate headers with ACS primary or secondary key
        $signature = $this->generateAuthenticationSignature($stringToSign);

        // get GUID part of message id to identify the long running operation
        $messageId = $this->generateMessageId();

        return [
            'Content-Type' => 'application/json',
            'repeatability-request-id' => $messageId,
            'Operation-Id' => $messageId,
            'repeatability-first-sent' => $utcNow,
            'x-ms-date' => $utcNow,
            'x-ms-content-sha256' => $contentHash,
            'x-ms-client-request-id' => $messageId,
            'Authorization' => "HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature={$signature}",
        ];
    }

    /**
     * Can be used to identify the long running operation.
     */
    private function generateMessageId(): string
    {
        $data = random_bytes(16);
        \assert(16 == \strlen($data));
        $data[6] = \chr(\ord($data[6]) & 0x0F | 0x40);
        $data[8] = \chr(\ord($data[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function getMessageCustomHeaders(Email $email): array
    {
        $headers = [];

        $headersToBypass = ['x-ms-client-request-id', 'operation-id', 'authorization', 'x-ms-content-sha256', 'received', 'dkim-signature', 'content-transfer-encoding', 'from', 'to', 'cc', 'bcc', 'subject', 'content-type', 'reply-to'];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }
            $headers[$header->getName()] = $header->getBodyAsString();
        }

        return $headers;
    }

    private function getPriorityLevel(string $priority): ?string
    {
        return match ((int) $priority) {
            Email::PRIORITY_HIGHEST => 'highest',
            Email::PRIORITY_HIGH => 'high',
            Email::PRIORITY_NORMAL => 'normal',
            Email::PRIORITY_LOW => 'low',
            Email::PRIORITY_LOWEST => 'lowest',
        };
    }
}
