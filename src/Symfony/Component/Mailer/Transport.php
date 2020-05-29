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
use Symfony\Component\Dsn\Configuration\Dsn;
use Symfony\Component\Dsn\Configuration\DsnFunction;
use Symfony\Component\Dsn\DsnParser;
use Symfony\Component\Dsn\Exception\FunctionNotSupportedException;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\Dsn as MailerDsn;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Symfony\Component\Mailer\Transport\NullTransportFactory;
use Symfony\Component\Mailer\Transport\RoundRobinTransport;
use Symfony\Component\Mailer\Transport\SendmailTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Transports;
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

    public static function fromDsns(array $dsns, EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null, LoggerInterface $logger = null): TransportInterface
    {
        $factory = new self(iterator_to_array(self::getDefaultFactories($dispatcher, $client, $logger)));

        return $factory->fromStrings($dsns);
    }

    /**
     * @param TransportFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    public function fromStrings(array $dsns): Transports
    {
        $transports = [];
        foreach ($dsns as $name => $dsn) {
            $transports[$name] = $this->fromString($dsn);
        }

        return new Transports($transports);
    }

    public function fromString(string $dsn): TransportInterface
    {
        return self::fromDsnComponent(DsnParser::parseFunc($dsn));
    }

    private function fromDsnComponent($dsn): TransportInterface
    {
        static $keywords = [
            'failover' => FailoverTransport::class,
            'roundrobin' => RoundRobinTransport::class,
        ];

        if ($dsn instanceof Dsn) {
            return $this->fromDsnObject(MailerDsn::fromUrlDsn($dsn));
        }

        if (!$dsn instanceof DsnFunction) {
            throw new \InvalidArgumentException(sprintf('First argument to Transport::fromDsnComponent() must be a "%s" or "%s".', DsnFunction::class, Dsn::class));
        }

        if (!isset($keywords[$dsn->getName()])) {
            if ('dsn' !== $dsn->getName()) {
                throw new FunctionNotSupportedException($dsn, $dsn->getName());
            }

            return $this->fromDsnObject(MailerDsn::fromUrlDsn($dsn->first()));
        }

        $class = $keywords[$dsn->getName()];

        return new $class(array_map(\Closure::fromCallable([self::class, 'fromDsnComponent']), $dsn->getArguments()));
    }

    public function fromDsnObject(MailerDsn $dsn): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn);
            }
        }

        throw new UnsupportedSchemeException($dsn);
    }

    public static function getDefaultFactories(EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null, LoggerInterface $logger = null): iterable
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
