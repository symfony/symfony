<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Utils;
use Microsoft\Graph\Generated\Models\BodyType;
use Microsoft\Graph\Generated\Models\EmailAddress;
use Microsoft\Graph\Generated\Models\FileAttachment;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Message;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\Generated\Models\Recipient;
use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Safe\Exceptions\JsonException;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Exception\SenderNotFoundException;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Exception\SendMailException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\Cache\CacheInterface;

use function Safe\json_decode;

class MicrosoftGraphTransport implements TransportInterface
{
    private GraphServiceClient $graphServiceClient;

    public function __construct(
        private readonly string $nationalCloud,
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly CacheInterface $cache,
    ) {
        $tokenRequestContext = new ClientCredentialContext(
            $this->tenantId,
            $this->clientId,
            $this->clientSecret
        );
        $this->graphServiceClient = new GraphServiceClient($tokenRequestContext, [], $this->nationalCloud);
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        $envelope = null !== $envelope ? clone $envelope : Envelope::create($message);

        if (!$message instanceof Email) {
            throw new SendEmailError(sprintf("This mailer can only handle mails of class '%s' or it's subclasses, instance of %s passed", Email::class, $message::class));
        }


        $this->sendMail($message);

        return new SentMessage($message, $envelope);
    }

    private function sendMail(Email $message): void
    {
        $message = $this->convertEmailToGraphMessage($message);
        $body = new SendMailPostRequestBody();
        $body->setMessage($message);
        // Make sure $senderAddress is the email of an account in the tenant
        $senderAddress = $message->getFrom()->getEmailAddress()->getAddress();

        try {
            $this->graphServiceClient->users()->byUserId($senderAddress)->sendMail()->post($body)->wait();
        } catch (ODataError $error) {
            if ('ErrorInvalidUser' === $error->getError()->getCode()){
                throw new SenderNotFoundException("Sender email address '".$senderAddress."' could not be found when calling the Graph API. This is usually because the email address doesn't exist in the tenant.", 404, $error);
            }
            throw new SendMailException('Something went wrong while sending email', $error->getCode(), $error);
        }
    }

    private function convertEmailToGraphMessage(Email $source): Message
    {
        $message = new Message();

        // From
        if (0 === \count($source->getFrom())) {
            throw new SendEmailError("Cannot send mail without 'From'");
        }

        $message->setFrom(self::convertAddressToGraphRecipient($source->getFrom()[0]));

        // to
        $message->setToRecipients(\array_map(
            static fn (Address $address) => self::convertAddressToGraphRecipient($address),
            $source->getTo()
        ));

        // CC
        $message->setCcRecipients(\array_map(
            static fn (Address $address) => self::convertAddressToGraphRecipient($address),
            $source->getCc()
        ));

        // BCC
        $message->setBccRecipients(\array_map(
            static fn (Address $address) => self::convertAddressToGraphRecipient($address),
            $source->getBcc()
        ));

        // Subject & body
        $message->setSubject($source->getSubject() ?? 'No subject');
        $itemBody = new ItemBody();
        $itemBody->setContent((string) $source->getHtmlBody());
        $itemBody->setContentType(new BodyType(BodyType::HTML));
        $message->setBody($itemBody);

        $message->setAttachments(\array_map(
            static fn (DataPart $attachment) => self::convertAttachmentGraphAttachment($attachment),
            $source->getAttachments()
        ));

        return $message;
    }

    private static function convertAddressToGraphRecipient(Address $source): Recipient
    {
        $recipient = new Recipient();
        $emailAddress = new EmailAddress();
        $emailAddress->setAddress($source->getAddress());
        $emailAddress->setName($source->getName());
        $recipient->setEmailAddress($emailAddress);
        return $recipient;
    }

    private static function convertAttachmentGraphAttachment(DataPart $source): FileAttachment
    {
        $attachment = new FileAttachment();

        $contentDisposition = $source->getPreparedHeaders()->get('content-disposition');
        \assert($contentDisposition instanceof ParameterizedHeader);
        $filename = $contentDisposition->getParameter('filename');

        $fileStream = Utils::streamFor($source->bodyToString());
        \assert($fileStream instanceof Stream);

        $attachment->setContentBytes($fileStream)
            ->setContentType($source->getMediaType().'/'.$source->getMediaSubtype())
            ->setName($filename)
            ->setODataType('#microsoft.graph.fileAttachment');

        return $attachment;
    }

    public function __toString(): string
    {
        return 'microsoft_graph://oauth_mail';
    }
}
