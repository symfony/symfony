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

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\Auth\AuthenticatorInterface;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * Sends Emails over SMTP with ESMTP support.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Chris Corbyn
 */
class EsmtpTransport extends SmtpTransport
{
    private array $authenticators = [];
    private string $username = '';
    private string $password = '';

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

    /**
     * @return $this
     */
    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return $this
     */
    public function setPassword(string $password): static
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
        if (!$capabilities = $this->callHeloCommand()) {
            return;
        }

        /** @var SocketStream $stream */
        $stream = $this->getStream();
        // WARNING: !$stream->isTLS() is right, 100% sure :)
        // if you think that the ! should be removed, read the code again
        // if doing so "fixes" your issue then it probably means your SMTP server behaves incorrectly or is wrongly configured
        if (!$stream->isTLS() && \defined('OPENSSL_VERSION_NUMBER') && \array_key_exists('STARTTLS', $capabilities)) {
            $this->executeCommand("STARTTLS\r\n", [220]);

            if (!$stream->startTLS()) {
                throw new TransportException('Unable to connect with STARTTLS.');
            }

            $capabilities = $this->callHeloCommand();
        }

        if (\array_key_exists('AUTH', $capabilities)) {
            $this->handleAuth($capabilities['AUTH']);
        }
    }

    private function callHeloCommand(): array
    {
        try {
            $response = $this->executeCommand(sprintf("EHLO %s\r\n", $this->getLocalDomain()), [250]);
        } catch (TransportExceptionInterface $e) {
            try {
                parent::doHeloCommand();

                return [];
            } catch (TransportExceptionInterface $ex) {
                if (!$ex->getCode()) {
                    throw $e;
                }

                throw $ex;
            }
        }

        $capabilities = [];
        $lines = explode("\r\n", trim($response));
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
                try {
                    $this->executeCommand("RSET\r\n", [250]);
                } catch (TransportExceptionInterface $_) {
                    // ignore this exception as it probably means that the server error was final
                }

                // keep the error message, but tries the other authenticators
                $errors[$authenticator->getAuthKeyword()] = $e->getMessage();
            }
        }

        if (!$authNames) {
            throw new TransportException(sprintf('Failed to find an authenticator supported by the SMTP server, which currently supports: "%s".', implode('", "', $modes)));
        }

        $message = sprintf('Failed to authenticate on SMTP server with username "%s" using the following authenticators: "%s".', $this->username, implode('", "', $authNames));
        foreach ($errors as $name => $error) {
            $message .= sprintf(' Authenticator "%s" returned "%s".', $name, $error);
        }

        throw new TransportException($message);
    }
}
