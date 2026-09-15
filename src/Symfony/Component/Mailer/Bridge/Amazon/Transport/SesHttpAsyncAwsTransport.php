<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Transport;

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\Core\Exception\Http\NetworkException;
use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\SesClient;
use AsyncAws\Ses\ValueObject\Destination;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class SesHttpAsyncAwsTransport extends AbstractTransport
{
    private ?string $tenant = null;

    public function __construct(
        protected SesClient $sesClient,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    public function setTenant(?string $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function __toString(): string
    {
        $configuration = $this->sesClient->getConfiguration();
        if (!$configuration->isDefault('endpoint')) {
            $endpoint = parse_url($configuration->get('endpoint'));
            $host = $endpoint['host'].($endpoint['port'] ?? null ? ':'.$endpoint['port'] : '');
        } else {
            $host = $configuration->get('region');
        }

        return \sprintf('ses+https://%s@%s', $configuration->get('accessKeyId'), $host);
    }

    protected function doSend(SentMessage $message): void
    {
        $result = $this->sesClient->sendEmail($this->getRequest($message));
        $response = $result->info()['response'];

        try {
            $message->setMessageId($result->getMessageId());
            $message->appendDebug($response->getInfo('debug') ?? '');
        } catch (HttpException $e) {
            $exception = new HttpTransportException(\sprintf('Unable to send an email: %s (code %s).', $e->getAwsMessage() ?: $e->getMessage(), $e->getAwsCode() ?: $e->getCode()), $e->getResponse(), $e->getCode(), $e);
            $exception->appendDebug($e->getResponse()->getInfo('debug') ?? '');

            throw $exception;
        } catch (NetworkException $e) {
            throw new HttpTransportException('Could not reach the remote Amazon server.', $response, 0, $e);
        }
    }

    protected function getRequest(SentMessage $message): SendEmailRequest
    {
        $request = [
            'Destination' => new Destination([
                'ToAddresses' => $this->stringifyAddresses($message->getEnvelope()->getRecipients()),
            ]),
            'Content' => [
                'Raw' => [
                    'Data' => $this->getRawData($message),
                ],
            ],
        ];

        $originalMessage = $message->getOriginalMessage();
        $tenant = $this->tenant;
        if ($originalMessage instanceof Message) {
            if ($configurationSetHeader = $originalMessage->getHeaders()->get('X-SES-CONFIGURATION-SET')) {
                $request['ConfigurationSetName'] = $configurationSetHeader->getBodyAsString();
            }
            if ($sourceArnHeader = $originalMessage->getHeaders()->get('X-SES-SOURCE-ARN')) {
                $request['FromEmailAddressIdentityArn'] = $sourceArnHeader->getBodyAsString();
            }
            if ($header = $originalMessage->getHeaders()->get('X-SES-LIST-MANAGEMENT-OPTIONS')) {
                if (preg_match('/^(contactListName=)*(?<ContactListName>[^;]+)(;\s?topicName=(?<TopicName>.+))?$/ix', $header->getBodyAsString(), $listManagementOptions)) {
                    $request['ListManagementOptions'] = array_filter($listManagementOptions, static fn ($e) => \in_array($e, ['ContactListName', 'TopicName'], true), \ARRAY_FILTER_USE_KEY);
                }
            }
            $tenant = $this->getTenant($originalMessage->getHeaders());
            foreach ($originalMessage->getHeaders()->all() as $header) {
                if ($header instanceof MetadataHeader) {
                    $request['EmailTags'][] = ['Name' => $header->getKey(), 'Value' => $header->getValue()];
                }
            }
        }

        if (null !== $tenant) {
            $request['TenantName'] = $tenant;
        }

        return new SendEmailRequest($request);
    }

    final protected function getTenant(Headers $headers): ?string
    {
        if ($header = $headers->get('X-SES-TENANT')) {
            return $header->getBodyAsString();
        }

        return $this->tenant;
    }

    private function getRawData(SentMessage $message): string
    {
        $originalMessage = $message->getOriginalMessage();

        if (!$originalMessage instanceof Message) {
            return $message->toString();
        }

        $metadataNames = [];
        foreach ($originalMessage->getHeaders()->all() as $name => $header) {
            if ($header instanceof MetadataHeader) {
                $metadataNames[] = $name;
            }
        }

        if (!$metadataNames) {
            return $message->toString();
        }

        // the metadata is sent as email tags, it must not leak into the delivered email
        $originalMessage = clone $originalMessage;
        $headers = $originalMessage->getHeaders();
        foreach ($metadataNames as $name) {
            $headers->remove($name);
        }
        if (!$headers->has('Message-ID')) {
            $headers->addIdHeader('Message-ID', $message->getMessageId());
        }

        return $originalMessage->toString();
    }
}
