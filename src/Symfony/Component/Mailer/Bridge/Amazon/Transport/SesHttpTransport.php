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
use Symfony\Component\Mailer\Bridge\Amazon\Credential\ApiTokenCredential;
use Symfony\Component\Mailer\Bridge\Amazon\Credential\UsernamePasswordCredential;
use Symfony\Component\Mailer\Bridge\Amazon\SesRequest;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractHttpTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class SesHttpTransport extends AbstractHttpTransport
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

        return sprintf('http://%s@ses?region=%s', $login, $this->region);
    }

    protected function doSendHttp(SentMessage $message): ResponseInterface
    {
        $request = new SesRequest($this->client, $this->region);
        $request->setMode(SesRequest::REQUEST_MODE_HTTP);
        $request->setCredential($this->credential);

        $response = $request->sendRawEmail($message->toString());

        if (200 !== $response->getStatusCode()) {
            $error = new \SimpleXMLElement($response->getContent(false));

            throw new HttpTransportException(sprintf('Unable to send an email: %s (code %s).', $error->Error->Message, $error->Error->Code), $response);
        }

        return $response;
    }
}
