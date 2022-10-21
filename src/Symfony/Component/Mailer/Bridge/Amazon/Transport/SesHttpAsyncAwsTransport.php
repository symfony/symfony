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
use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\SesClient;
use AsyncAws\Ses\ValueObject\Destination;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Message;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class SesHttpAsyncAwsTransport extends AbstractTransport
{
    /** @var SesClient */
    protected $sesClient;

    public function __construct(SesClient $sesClient, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->sesClient = $sesClient;

        parent::__construct($dispatcher, $logger);
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

        return sprintf('ses+https://%s@%s', $configuration->get('accessKeyId'), $host);
    }

    protected function doSend(SentMessage $message): void
    {
        $result = $this->sesClient->sendEmail($this->getRequest($message));
        $response = $result->info()['response'];

        try {
            $message->setMessageId($result->getMessageId());
            $message->appendDebug($response->getInfo('debug') ?? '');
        } catch (HttpException $e) {
            $exception = new HttpTransportException(sprintf('Unable to send an email: %s (code %s).', $e->getAwsMessage() ?: $e->getMessage(), $e->getAwsCode() ?: $e->getCode()), $e->getResponse(), $e->getCode(), $e);
            $exception->appendDebug($e->getResponse()->getInfo('debug') ?? '');

            throw $exception;
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
                    'Data' => $message->toString(),
                ],
            ],
        ];

        if (($message->getOriginalMessage() instanceof Message)
            && $configurationSetHeader = $message->getOriginalMessage()->getHeaders()->get('X-SES-CONFIGURATION-SET')) {
            $request['ConfigurationSetName'] = $configurationSetHeader->getBodyAsString();
        }
        if (($message->getOriginalMessage() instanceof Message)
            && $sourceArnHeader = $message->getOriginalMessage()->getHeaders()->get('X-SES-SOURCE-ARN')) {
            $request['FromEmailAddressIdentityArn'] = $sourceArnHeader->getBodyAsString();
        }
        if ($message->getOriginalMessage() instanceof Message) {
            foreach ($message->getOriginalMessage()->getHeaders()->all() as $header) {
                if ($header instanceof MetadataHeader) {
                    $request['EmailTags'][] = ['Name' => $header->getKey(), 'Value' => $header->getValue()];
                }
            }
        }

        return new SendEmailRequest($request);
    }
}
