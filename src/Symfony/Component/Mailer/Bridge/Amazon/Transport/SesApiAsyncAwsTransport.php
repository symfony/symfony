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

use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\ValueObject\Content;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\RemoteTemplateEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\RemoteTemplateTransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class SesApiAsyncAwsTransport extends SesHttpAsyncAwsTransport implements RemoteTemplateTransportInterface
{
    public function __toString(): string
    {
        $configuration = $this->sesClient->getConfiguration();
        if (!$configuration->isDefault('endpoint')) {
            $endpoint = parse_url($configuration->get('endpoint'));
            $host = $endpoint['host'].($endpoint['port'] ?? null ? ':'.$endpoint['port'] : '');
        } else {
            $host = $configuration->get('region');
        }

        return \sprintf('ses+api://%s@%s', $configuration->get('accessKeyId'), $host);
    }

    protected function getRequest(SentMessage $message): SendEmailRequest
    {
        $originalMessage = $message->getOriginalMessage();

        if (!$originalMessage instanceof Message) {
            // the raw endpoint takes the message as-is, the same way an email with attachments does
            return parent::getRequest($message);
        }

        try {
            $email = MessageConverter::toEmail($originalMessage);
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('Unable to send message with the "%s" transport: ', __CLASS__).$e->getMessage(), 0, $e);
        }

        $template = $email instanceof RemoteTemplateEmail ? $email->getRemoteTemplate() : null;

        if ($email->getAttachments()) {
            if (null !== $template) {
                throw new InvalidArgumentException('The Amazon SES API does not support attachments when using a remote template.');
            }

            return parent::getRequest($message);
        }

        $envelope = $message->getEnvelope();

        $request = [
            'FromEmailAddress' => $this->stringifyAddress(self::getSenderFromHeaders($email->getHeaders())),
            'Destination' => [
                'ToAddresses' => $this->stringifyAddresses($this->getRecipients($email, $envelope)),
            ],
        ];
        if (null !== $template) {
            if (null !== $email->getSubject()) {
                throw new InvalidArgumentException('Amazon SES does not support overriding the subject of a template; define the subject in the template itself.');
            }
            $request['Content'] = [
                'Template' => [
                    'TemplateName' => $template->getReference(),
                    'TemplateData' => json_encode($template->getVariables() ?: new \stdClass(), \JSON_THROW_ON_ERROR),
                ],
            ];
        } else {
            $request['Content'] = [
                'Simple' => [
                    'Subject' => [
                        'Data' => $email->getSubject(),
                        'Charset' => 'utf-8',
                    ],
                    'Body' => [],
                ],
            ];
        }

        if ($emails = $email->getCc()) {
            $request['Destination']['CcAddresses'] = $this->stringifyAddresses($emails);
        }
        if ($emails = $email->getBcc()) {
            $request['Destination']['BccAddresses'] = $this->stringifyAddresses($emails);
        }
        if ($email->getTextBody()) {
            $request['Content']['Simple']['Body']['Text'] = new Content([
                'Data' => $email->getTextBody(),
                'Charset' => $email->getTextCharset(),
            ]);
        }
        if ($email->getHtmlBody()) {
            $request['Content']['Simple']['Body']['Html'] = new Content([
                'Data' => $email->getHtmlBody(),
                'Charset' => $email->getHtmlCharset(),
            ]);
        }
        if ($emails = $email->getReplyTo()) {
            $request['ReplyToAddresses'] = $this->stringifyAddresses($emails);
        }
        if ($header = $email->getHeaders()->get('X-SES-CONFIGURATION-SET')) {
            $request['ConfigurationSetName'] = $header->getBodyAsString();
        }
        if ($header = $email->getHeaders()->get('X-SES-SOURCE-ARN')) {
            $request['FromEmailAddressIdentityArn'] = $header->getBodyAsString();
        }
        if ($header = $email->getHeaders()->get('X-SES-LIST-MANAGEMENT-OPTIONS')) {
            if (preg_match('/^(contactListName=)*(?<ContactListName>[^;]+)(;\s?topicName=(?<TopicName>.+))?$/ix', $header->getBodyAsString(), $listManagementOptions)) {
                $request['ListManagementOptions'] = array_filter($listManagementOptions, static fn ($e) => \in_array($e, ['ContactListName', 'TopicName'], true), \ARRAY_FILTER_USE_KEY);
            }
        }
        if ($email->getReturnPath()) {
            $request['FeedbackForwardingEmailAddress'] = $email->getReturnPath()->toString();
        }

        if ($customHeaders = $this->getCustomHeaders($email->getHeaders())) {
            if (null !== $template) {
                $request['Content']['Template']['Headers'] = $customHeaders;
            } else {
                $request['Content']['Simple']['Headers'] = $customHeaders;
            }
        }

        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof MetadataHeader) {
                $request['EmailTags'][] = ['Name' => $header->getKey(), 'Value' => $header->getValue()];
            }
        }

        return new SendEmailRequest($request);
    }

    private function getRecipients(Email $email, Envelope $envelope): array
    {
        $emailRecipients = array_merge($email->getCc(), $email->getBcc());

        return array_filter($envelope->getRecipients(), static fn (Address $address) => !\in_array($address, $emailRecipients, true));
    }

    private function getCustomHeaders(Headers $headers): array
    {
        $headersPrepared = [];

        $headersToBypass = ['from', 'to', 'cc', 'bcc', 'return-path', 'subject', 'reply-to', 'sender', 'content-type', 'x-ses-configuration-set', 'x-ses-source-arn', 'x-ses-list-management-options'];
        foreach ($headers->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }

            if ($header instanceof MetadataHeader) {
                continue;
            }

            $value = $header->getBodyAsString();

            // AWS SES Simple message headers only accept printable ASCII (char codes 32-126).
            // getBodyAsString() may produce encoded words with \r\n line folding, so we
            // re-encode using RFC 2047 base64 encoding when non-printable characters are present.
            if (preg_match('/[^\x20-\x7E]/', $value)) {
                $value = '=?UTF-8?B?'.base64_encode($header->getBody()).'?=';
            }

            $headersPrepared[] = [
                'Name' => $header->getName(),
                'Value' => $value,
            ];
        }

        return $headersPrepared;
    }

    protected function stringifyAddresses(array $addresses): array
    {
        return array_map(fn (Address $a) => $this->stringifyAddress($a), $addresses);
    }

    protected function stringifyAddress(Address $a): string
    {
        // AWS does not support UTF-8 address
        if (preg_match('~[\x00-\x08\x10-\x19\x7F-\xFF\r\n]~', $name = $a->getName())) {
            return \sprintf('=?UTF-8?B?%s?= <%s>',
                base64_encode($name),
                $a->getEncodedAddress()
            );
        }

        return $a->toString();
    }

    private static function getSenderFromHeaders(Headers $headers): Address
    {
        if ($sender = $headers->get('Sender')) {
            return $sender->getAddress();
        }
        if ($from = $headers->get('From')) {
            return $from->getAddresses()[0];
        }
        if ($return = $headers->get('Return-Path')) {
            return $return->getAddress();
        }

        throw new LogicException('Unable to determine the sender of the message.');
    }
}
