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

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SmtpEnvelope;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class SesApiTransport extends AbstractApiTransport
{
    private $credential;
    private $region;

    /**
     * @param ApiTokenCredential|UsernamePasswordCredential $credential credential object for SES authentication. ApiTokenCredential and UsernamePasswordCredential are supported.
     * @param string                                        $region     Amazon SES region (currently one of us-east-1, us-west-2, or eu-west-1)
     */
    public function __construct($credential, string $region = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->credential = $credential;
        $this->region = $region ?: 'eu-west-1';

        parent::__construct($client, $dispatcher, $logger);
    }

    public function getName(): string
    {
        $login = null;
        if ($this->credential instanceof ApiTokenCredential) {
            $login = $this->credential->getAccessKey();
        } else {
            $login = $this->credential->getUsername();
        }

        return sprintf('api://%s@ses?region=%s', $login, $this->region);
    }

    protected function doSendApi(Email $email, SmtpEnvelope $envelope): ResponseInterface
    {
        $request = new SesRequest($this->client, $this->region);
        $request->setMode(SesRequest::REQUEST_MODE_API);
        $request->setCredential($this->credential);

        if ($email->getAttachments()) {
            $response = $request->sendRawEmail($email->toString());
        } else {
            $response = $request->sendEmail($email, $envelope);
        }

        if (200 !== $response->getStatusCode()) {
            $error = new \SimpleXMLElement($response->getContent(false));

            throw new HttpTransportException(sprintf('Unable to send an email: %s (code %s).', $error->Error->Message, $error->Error->Code), $response);
        }

        return $response;
    }
}
