<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Bridge\Amazon;
use Symfony\Component\Mailer\Bridge\Google;
use Symfony\Component\Mailer\Bridge\Mailchimp;
use Symfony\Component\Mailer\Bridge\Mailgun;
use Symfony\Component\Mailer\Bridge\Postmark;
use Symfony\Component\Mailer\Bridge\Sendgrid;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 4.3
 */
class Transport
{
    public static function fromDsn(string $dsn, EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null, LoggerInterface $logger = null): TransportInterface
    {
        // failover?
        $dsns = preg_split('/\s++\|\|\s++/', $dsn);
        if (\count($dsns) > 1) {
            $transports = [];
            foreach ($dsns as $dsn) {
                $transports[] = self::createTransport($dsn, $dispatcher, $client, $logger);
            }

            return new Transport\FailoverTransport($transports);
        }

        // round robin?
        $dsns = preg_split('/\s++&&\s++/', $dsn);
        if (\count($dsns) > 1) {
            $transports = [];
            foreach ($dsns as $dsn) {
                $transports[] = self::createTransport($dsn, $dispatcher, $client, $logger);
            }

            return new Transport\RoundRobinTransport($transports);
        }

        return self::createTransport($dsn, $dispatcher, $client, $logger);
    }

    private static function createTransport(string $dsn, EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null, LoggerInterface $logger = null): TransportInterface
    {
        if (false === $parsedDsn = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The "%s" mailer DSN is invalid.', $dsn));
        }

        if (!isset($parsedDsn['scheme'])) {
            throw new InvalidArgumentException(sprintf('The "%s" mailer DSN must contain a transport scheme.', $dsn));
        }

        if (!isset($parsedDsn['host'])) {
            throw new InvalidArgumentException(sprintf('The "%s" mailer DSN must contain a mailer name.', $dsn));
        }

        $user = urldecode($parsedDsn['user'] ?? '');
        $pass = urldecode($parsedDsn['pass'] ?? '');
        parse_str($parsedDsn['query'] ?? '', $query);

        switch ($parsedDsn['host']) {
            case 'null':
                if ('smtp' === $parsedDsn['scheme']) {
                    return new Transport\NullTransport($dispatcher, $logger);
                }

                throw new LogicException(sprintf('The "%s" scheme is not supported for mailer "%s".', $parsedDsn['scheme'], $parsedDsn['host']));
            case 'sendmail':
                if ('smtp' === $parsedDsn['scheme']) {
                    return new Transport\SendmailTransport(null, $dispatcher, $logger);
                }

                throw new LogicException(sprintf('The "%s" scheme is not supported for mailer "%s".', $parsedDsn['scheme'], $parsedDsn['host']));
            case 'gmail':
                if (!class_exists(Google\Smtp\GmailTransport::class)) {
                    throw new \LogicException('Unable to send emails via Gmail as the Google bridge is not installed. Try running "composer require symfony/google-mailer".');
                }

                if ('smtp' === $parsedDsn['scheme']) {
                    return new Google\Smtp\GmailTransport($user, $pass, $dispatcher, $logger);
                }

                throw new LogicException(sprintf('The "%s" scheme is not supported for mailer "%s".', $parsedDsn['scheme'], $parsedDsn['host']));
            case 'mailgun':
                if (!class_exists(Mailgun\Smtp\MailgunTransport::class)) {
                    throw new \LogicException('Unable to send emails via Mailgun as the bridge is not installed. Try running "composer require symfony/mailgun-mailer".');
                }

                if ('smtp' === $parsedDsn['scheme']) {
                    return new Mailgun\Smtp\MailgunTransport($user, $pass, $query['region'] ?? null, $dispatcher, $logger);
                }
                if ('http' === $parsedDsn['scheme']) {
                    return new Mailgun\Http\MailgunTransport($user, $pass, $query['region'] ?? null, $client, $dispatcher, $logger);
                }
                if ('api' === $parsedDsn['scheme']) {
                    return new Mailgun\Http\Api\MailgunTransport($user, $pass, $query['region'] ?? null, $client, $dispatcher, $logger);
                }

                throw new LogicException(sprintf('The "%s" scheme is not supported for mailer "%s".', $parsedDsn['scheme'], $parsedDsn['host']));
            case 'postmark':
                if (!class_exists(Postmark\Smtp\PostmarkTransport::class)) {
                    throw new \LogicException('Unable to send emails via Postmark as the bridge is not installed. Try running "composer require symfony/postmark-mailer".');
                }

                if ('smtp' === $parsedDsn['scheme']) {
                    return new Postmark\Smtp\PostmarkTransport($user, $dispatcher, $logger);
                }
                if ('api' === $parsedDsn['scheme']) {
                    return new Postmark\Http\Api\PostmarkTransport($user, $client, $dispatcher, $logger);
                }

                throw new LogicException(sprintf('The "%s" scheme is not supported for mailer "%s".', $parsedDsn['scheme'], $parsedDsn['host']));
            case 'sendgrid':
                if (!class_exists(Sendgrid\Smtp\SendgridTransport::class)) {
                    throw new \LogicException('Unable to send emails via Sendgrid as the bridge is not installed. Try running "composer require symfony/sendgrid-mailer".');
                }

                if ('smtp' === $parsedDsn['scheme']) {
                    return new Sendgrid\Smtp\SendgridTransport($user, $dispatcher, $logger);
                }
                if ('api' === $parsedDsn['scheme']) {
                    return new Sendgrid\Http\Api\SendgridTransport($user, $client, $dispatcher, $logger);
                }

                throw new LogicException(sprintf('The "%s" scheme is not supported for mailer "%s".', $parsedDsn['scheme'], $parsedDsn['host']));
            case 'ses':
                if (!class_exists(Amazon\Smtp\SesTransport::class)) {
                    throw new \LogicException('Unable to send emails via Amazon SES as the bridge is not installed. Try running "composer require symfony/amazon-mailer".');
                }

                if ('smtp' === $parsedDsn['scheme']) {
                    return new Amazon\Smtp\SesTransport($user, $pass, $query['region'] ?? null, $dispatcher, $logger);
                }
                if ('api' === $parsedDsn['scheme']) {
                    return new Amazon\Http\Api\SesTransport($user, $pass, $query['region'] ?? null, $client, $dispatcher, $logger);
                }
                if ('http' === $parsedDsn['scheme']) {
                    return new Amazon\Http\SesTransport($user, $pass, $query['region'] ?? null, $client, $dispatcher, $logger);
                }

                throw new LogicException(sprintf('The "%s" scheme is not supported for mailer "%s".', $parsedDsn['scheme'], $parsedDsn['host']));
            case 'mandrill':
                if (!class_exists(Mailchimp\Smtp\MandrillTransport::class)) {
                    throw new \LogicException('Unable to send emails via Mandrill as the bridge is not installed. Try running "composer require symfony/mailchimp-mailer".');
                }

                if ('smtp' === $parsedDsn['scheme']) {
                    return new Mailchimp\Smtp\MandrillTransport($user, $pass, $dispatcher, $logger);
                }
                if ('api' === $parsedDsn['scheme']) {
                    return new Mailchimp\Http\Api\MandrillTransport($user, $client, $dispatcher, $logger);
                }
                if ('http' === $parsedDsn['scheme']) {
                    return new Mailchimp\Http\MandrillTransport($user, $client, $dispatcher, $logger);
                }

                throw new LogicException(sprintf('The "%s" scheme is not supported for mailer "%s".', $parsedDsn['scheme'], $parsedDsn['host']));
            default:
                if ('smtp' === $parsedDsn['scheme']) {
                    $transport = new Transport\Smtp\EsmtpTransport($parsedDsn['host'], $parsedDsn['port'] ?? 25, $query['encryption'] ?? null, $query['auth_mode'] ?? null, $dispatcher, $logger);

                    if ($user) {
                        $transport->setUsername($user);
                    }

                    if ($pass) {
                        $transport->setPassword($pass);
                    }

                    return $transport;
                }

                throw new LogicException(sprintf('The "%s" mailer is not supported.', $parsedDsn['host']));
        }
    }
}
