<?php
declare(strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport;

use DateInterval;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Utils;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\BodyType;
use Microsoft\Graph\Model\EmailAddress;
use Microsoft\Graph\Model\FileAttachment;
use Microsoft\Graph\Model\ItemBody;
use Microsoft\Graph\Model\Message;
use Microsoft\Graph\Model\Recipient;
use Safe\Exceptions\JsonException;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Exception\SenderNotFoundException;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Exception\SendMailException;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Exception\UnAuthorizedException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function array_map;
use function assert;
use function count;
use function Safe\json_decode;

class MicrosoftGraphTransport implements TransportInterface
{
    public const AUTH_TOKEN_CACHE_KEY = 'SENDMAIL_OAUTH_TOKEN';
    private Graph $graph;

    public function __construct(
        private readonly string         $clientId,
        private readonly string         $clientSecret,
        private readonly string         $authEndpoint,
        private readonly string         $graphEndpoint,
        private readonly CacheInterface $cache,
    )
    {
        $this->graph = new Graph();
        $this->graph->setBaseUrl($graphEndpoint);
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $envelope = $envelope !== null ? clone $envelope : Envelope::create($message);

        if (!$message instanceof Email) {
            throw new SendEmailError(
                \sprintf(
                    "This mailer can only handle mails of class '%s' or it's subclasses, instance of %s passed",
                    Email::class,
                    $message::class
                ),
            );
        }

        $this->auth();

        try {
            $this->sendMail($message);
        } catch (UnAuthorizedException $exception) {
            // Token may have expired, we need to refresh the token and try again
            $this->auth(refresh: true);
            $this->sendMail($message);
        }

        return new SentMessage($message, $envelope);
    }

    private function sendMail(Email $message): void
    {
        $message = $this->convertEmailToGraphMessage($message);

        //Make sure $message->getFrom()->getEmailAddress()->getAddress() returns the email of an account in the tenant
        $senderAddress = $message->getFrom()->getEmailAddress()->getAddress();
        try{
            $this->graph->createRequest('POST', '/users/' . $senderAddress . '/sendMail')
                ->attachBody(['message' => $message])
                ->execute();
        }catch (ClientException $clientException){
            $statusCode = $clientException->getCode();
            if ($statusCode === 401) {
                throw new UnAuthorizedException("Send mail request failed: received 401 - Unauthorized", $statusCode, $clientException);
            }else if ($statusCode === 404){
                $responseBody = $clientException->getResponse()->getBody()->getContents();
                try{
                    $responseBody = json_decode($responseBody, true);
                    if ($responseBody['error']['code'] === 'ErrorInvalidUser') {
                        var_export($responseBody);
                        throw new SenderNotFoundException("Sender email address '" . $senderAddress . "' could not be found when calling the Graph API. This is usually because the email address doesn't exist in the tenant.", 404, $clientException);
                    }
                }catch (JsonException){
                    // no JSON content, silently ignore, will bubble up at the end
                }
            }
            throw new SendMailException("Something went wrong while sending email", $statusCode, $clientException);
        }
    }

    private function auth(bool $refresh = false): void
    {
        if ($refresh) {
            $this->cache->delete(key: self::AUTH_TOKEN_CACHE_KEY);
        }

        $accessToken = $this->cache->get(
            key: self::AUTH_TOKEN_CACHE_KEY,
            callback: function (ItemInterface $item) {
                $guzzle = new Client();
                $response = $guzzle->post($this->authEndpoint, [
                    'form_params' => [
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'scope' => $this->graphEndpoint . '/.default',
                        'grant_type' => 'client_credentials',
                    ],
                ])->getBody()->getContents();
                $token  = json_decode($response, true);

                $item->expiresAfter(new DateInterval('PT' . ($token['expires_in'] - 60) . 'S'));

                return $token['access_token'];
            }
        );

        $this->graph->setAccessToken($accessToken);
    }

    private function convertEmailToGraphMessage(Email $source): Message
    {
        $message = new Message();

        // From
        if (count($source->getFrom()) === 0) {
            throw new SendEmailError("Cannot send mail without 'From'");
        }

        $message->setFrom(self::convertAddressToGraphRecipient($source->getFrom()[0]));

        // to
        $message->setToRecipients(array_map(
            static fn(Address $address) => self::convertAddressToGraphRecipient($address),
            $source->getTo()
        ));

        // CC
        $message->setCcRecipients(array_map(
            static fn(Address $address) => self::convertAddressToGraphRecipient($address),
            $source->getCc()
        ));

        // BCC
        $message->setBccRecipients(array_map(
            static fn(Address $address) => self::convertAddressToGraphRecipient($address),
            $source->getBcc()
        ));

        // Subject & body
        $message->setSubject($source->getSubject() ?? 'No subject');
        $message->setBody(
            (new ItemBody())->setContent((string)$source->getHtmlBody())
                ->setContentType((new BodyType(BodyType::HTML)))
        );

        $message->setAttachments(array_map(
            static fn(DataPart $attachment) => self::convertAttachmentGraphAttachment($attachment),
            $source->getAttachments()
        ));

        return $message;
    }

    private static function convertAddressToGraphRecipient(Address $source): Recipient
    {
        return (new Recipient())
            ->setEmailAddress((new EmailAddress())
                ->setAddress($source->getAddress())
                ->setName($source->getName()));
    }

    private static function convertAttachmentGraphAttachment(DataPart $source): FileAttachment
    {
        $attachment = new FileAttachment();

        $contentDisposition = $source->getPreparedHeaders()->get('content-disposition');
        assert($contentDisposition instanceof ParameterizedHeader);
        $filename = $contentDisposition->getParameter('filename');

        $fileStream = Utils::streamFor($source->bodyToString());
        assert($fileStream instanceof Stream);

        $attachment->setContentBytes($fileStream)
            ->setContentType($source->getMediaType() . '/' . $source->getMediaSubtype())
            ->setName($filename)
            ->setODataType('#microsoft.graph.fileAttachment');

        return $attachment;
    }

    public function __toString(): string
    {
        return 'microsoft_graph://oauth_mail';
    }
}
