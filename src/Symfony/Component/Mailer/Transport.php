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
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedHostException;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Symfony\Component\Mailer\Transport\NullTransportFactory;
use Symfony\Component\Mailer\Transport\RoundRobinTransport;
use Symfony\Component\Mailer\Transport\SendmailTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
class Transport
{
    private const FACTORY_CLASSES = [
        SesTransportFactory::class,
        GmailTransportFactory::class,
        MandrillTransportFactory::class,
        MailgunTransportFactory::class,
        PostmarkTransportFactory::class,
        SendgridTransportFactory::class,
    ];

    private $factories;

    public static function fromDsn(string $dsn, EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null, LoggerInterface $logger = null): TransportInterface
    {
        $factory = new self(self::getDefaultFactories($dispatcher, $client, $logger));

        return $factory->fromString($dsn);
    }

    /**
     * @param TransportFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    public function fromString(string $dsn): TransportInterface
    {
        $dsns = preg_split('/\s++\|\|\s++/', $dsn);
        if (\count($dsns) > 1) {
            return new FailoverTransport($this->createFromDsns($dsns));
        }

        $dsns = preg_split('/\s++&&\s++/', $dsn);
        if (\count($dsns) > 1) {
            return new RoundRobinTransport($this->createFromDsns($dsns));
        }

        return $this->fromDsnObject(Dsn::fromString($dsn));
    }

    public function fromDsnObject(Dsn $dsn): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn);
            }
        }

        throw new UnsupportedHostException($dsn);
    }

    /**
     * @param string[] $dsns
     *
     * @return TransportInterface[]
     */
    private function createFromDsns(array $dsns): array
    {
        $transports = [];
        foreach ($dsns as $dsn) {
            $transports[] = $this->fromDsnObject(Dsn::fromString($dsn));
        }

        return $transports;
    }

    private static function getDefaultFactories(EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null, LoggerInterface $logger = null): iterable
    {
        foreach (self::FACTORY_CLASSES as $factoryClass) {
            if (class_exists($factoryClass)) {
                yield new $factoryClass($dispatcher, $client, $logger);
            }
        }

        yield new NullTransportFactory($dispatcher, $client, $logger);

        yield new SendmailTransportFactory($dispatcher, $client, $logger);

        yield new EsmtpTransportFactory($dispatcher, $client, $logger);
    }
}
