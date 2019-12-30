<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport\Smtp;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\Auth\AuthenticatorInterface;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Sends Emails over SMTP with ESMTP support.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Chris Corbyn
 */
class EsmtpTransport extends SmtpTransport
{
    private $authenticators = [];
    private $username = '';
    private $password = '';

    public function __construct(string $host = 'localhost', int $port = 0, bool $tls = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct(null, $dispatcher, $logger);

        // order is important here (roughly most secure and popular first)
        $this->authenticators = [
            new Auth\CramMd5Authenticator(),
            new Auth\LoginAuthenticator(),
            new Auth\PlainAuthenticator(),
            new Auth\XOAuth2Authenticator(),
        ];

        /** @var SocketStream $stream */
        $stream = $this->getStream();

        if (null === $tls) {
            if (465 === $port) {
                $tls = true;
            } else {
                $tls = \defined('OPENSSL_VERSION_NUMBER') && 0 === $port && 'localhost' !== $host;
            }
        }
        if (!$tls) {
            $stream->disableTls();
        }
        if (0 === $port) {
            $port = $tls ? 465 : 25;
        }

        $stream->setHost($host);
        $stream->setPort($port);
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function addAuthenticator(AuthenticatorInterface $authenticator): void
    {
        $this->authenticators[] = $authenticator;
    }

    protected function doHeloCommand(): void
    {
        try {
            $response = $this->executeCommand(sprintf("EHLO %s\r\n", $this->getLocalDomain()), [250]);
        } catch (TransportExceptionInterface $e) {
            parent::doHeloCommand();

            return;
        }

        $capabilities = $this->getCapabilities($response);

        /** @var SocketStream $stream */
        $stream = $this->getStream();
        if (!$stream->isTLS() && \defined('OPENSSL_VERSION_NUMBER') && \array_key_exists('STARTTLS', $capabilities)) {
            $this->executeCommand("STARTTLS\r\n", [220]);

            if (!$stream->startTLS()) {
                throw new TransportException('Unable to connect with STARTTLS.');
            }

            try {
                $response = $this->executeCommand(sprintf("EHLO %s\r\n", $this->getLocalDomain()), [250]);
            } catch (TransportExceptionInterface $e) {
                parent::doHeloCommand();

                return;
            }
        }

        if (\array_key_exists('AUTH', $capabilities)) {
            $this->handleAuth($capabilities['AUTH']);
        }
    }

    private function getCapabilities(string $ehloResponse): array
    {
        $capabilities = [];
        $lines = explode("\r\n", trim($ehloResponse));
        array_shift($lines);
        foreach ($lines as $line) {
            if (preg_match('/^[0-9]{3}[ -]([A-Z0-9-]+)((?:[ =].*)?)$/Di', $line, $matches)) {
                $value = strtoupper(ltrim($matches[2], ' ='));
                $capabilities[strtoupper($matches[1])] = $value ? explode(' ', $value) : [];
            }
        }

        return $capabilities;
    }

    private function handleAuth(array $modes): void
    {
        if (!$this->username) {
            return;
        }

        $authNames = [];
        $errors = [];
        $modes = array_map('strtolower', $modes);
        foreach ($this->authenticators as $authenticator) {
            if (!\in_array(strtolower($authenticator->getAuthKeyword()), $modes, true)) {
                continue;
            }

            $authNames[] = $authenticator->getAuthKeyword();
            try {
                $authenticator->authenticate($this);

                return;
            } catch (TransportExceptionInterface $e) {
                $this->executeCommand("RSET\r\n", [250]);

                // keep the error message, but tries the other authenticators
                $errors[$authenticator->getAuthKeyword()] = $e;
            }
        }

        if (!$authNames) {
            throw new TransportException('Failed to find an authenticator supported by the SMTP server.');
        }

        $message = sprintf('Failed to authenticate on SMTP server with username "%s" using the following authenticators: "%s".', $this->username, implode('", "', $authNames));
        foreach ($errors as $name => $error) {
            $message .= sprintf(' Authenticator "%s" returned "%s".', $name, $error);
        }

        throw new TransportException($message);
    }
}
