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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\Auth\AuthenticatorInterface;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * Sends Emails over SMTP with ESMTP support.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Chris Corbyn
 *
 * @experimental in 4.3
 */
class EsmtpTransport extends SmtpTransport
{
    private $authenticators = [];
    private $username = '';
    private $password = '';
    private $authMode;

    public function __construct(string $host = 'localhost', int $port = 25, string $encryption = null, string $authMode = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct(null, $dispatcher, $logger);

        $this->authenticators = [
            new Auth\CramMd5Authenticator(),
            new Auth\LoginAuthenticator(),
            new Auth\PlainAuthenticator(),
            new Auth\XOAuth2Authenticator(),
        ];

        /** @var SocketStream $stream */
        $stream = $this->getStream();
        $stream->setHost($host);
        $stream->setPort($port);
        if (null !== $encryption) {
            $stream->setEncryption($encryption);
        }
        if (null !== $authMode) {
            $this->setAuthMode($authMode);
        }
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

    public function setAuthMode(string $mode): self
    {
        $this->authMode = $mode;

        return $this;
    }

    public function getAuthMode(): string
    {
        return $this->authMode;
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

        /** @var SocketStream $stream */
        $stream = $this->getStream();
        if ($stream->isTLS()) {
            $this->executeCommand("STARTTLS\r\n", [220]);

            if (!$stream->startTLS()) {
                throw new TransportException('Unable to connect with TLS encryption.');
            }

            try {
                $response = $this->executeCommand(sprintf("EHLO %s\r\n", $this->getLocalDomain()), [250]);
            } catch (TransportExceptionInterface $e) {
                parent::doHeloCommand();

                return;
            }
        }

        $capabilities = $this->getCapabilities($response);
        if (\array_key_exists('AUTH', $capabilities)) {
            $this->handleAuth($capabilities['AUTH']);
        }
    }

    private function getCapabilities($ehloResponse): array
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
        foreach ($this->getActiveAuthenticators() as $authenticator) {
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

    /**
     * @return AuthenticatorInterface[]
     */
    private function getActiveAuthenticators(): array
    {
        if (!$mode = strtolower($this->authMode)) {
            return $this->authenticators;
        }

        foreach ($this->authenticators as $authenticator) {
            if (strtolower($authenticator->getAuthKeyword()) === $mode) {
                return [$authenticator];
            }
        }

        throw new TransportException(sprintf('Auth mode "%s" is invalid.', $mode));
    }
}
