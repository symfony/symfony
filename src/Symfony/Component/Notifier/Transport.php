<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\FailoverTransport;
use Symfony\Component\Notifier\Transport\NullTransportFactory;
use Symfony\Component\Notifier\Transport\RoundRobinTransport;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Component\Notifier\Transport\Transports;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Transport
{
    private const FACTORY_CLASSES = [
        Bridge\AllMySms\AllMySmsTransportFactory::class,
        Bridge\AmazonSns\AmazonSnsTransportFactory::class,
        Bridge\Bandwidth\BandwidthTransportFactory::class,
        Bridge\Bluesky\BlueskyTransportFactory::class,
        Bridge\Brevo\BrevoTransportFactory::class,
        Bridge\Chatwork\ChatworkTransportFactory::class,
        Bridge\Clickatell\ClickatellTransportFactory::class,
        Bridge\ClickSend\ClickSendTransportFactory::class,
        Bridge\ContactEveryone\ContactEveryoneTransportFactory::class,
        Bridge\Discord\DiscordTransportFactory::class,
        Bridge\Engagespot\EngagespotTransportFactory::class,
        Bridge\Esendex\EsendexTransportFactory::class,
        Bridge\Expo\ExpoTransportFactory::class,
        Bridge\FakeChat\FakeChatTransportFactory::class,
        Bridge\FakeSms\FakeSmsTransportFactory::class,
        Bridge\Firebase\FirebaseTransportFactory::class,
        Bridge\FortySixElks\FortySixElksTransportFactory::class,
        Bridge\FreeMobile\FreeMobileTransportFactory::class,
        Bridge\GatewayApi\GatewayApiTransportFactory::class,
        Bridge\GoIp\GoIpTransportFactory::class,
        Bridge\GoogleChat\GoogleChatTransportFactory::class,
        Bridge\Infobip\InfobipTransportFactory::class,
        Bridge\Iqsms\IqsmsTransportFactory::class,
        Bridge\Isendpro\IsendproTransportFactory::class,
        Bridge\JoliNotif\JoliNotifTransportFactory::class,
        Bridge\KazInfoTeh\KazInfoTehTransportFactory::class,
        Bridge\LightSms\LightSmsTransportFactory::class,
        Bridge\LineNotify\LineNotifyTransportFactory::class,
        Bridge\LinkedIn\LinkedInTransportFactory::class,
        Bridge\Lox24\Lox24TransportFactory::class,
        Bridge\Mailjet\MailjetTransportFactory::class,
        Bridge\Mastodon\MastodonTransportFactory::class,
        Bridge\Mattermost\MattermostTransportFactory::class,
        Bridge\Mercure\MercureTransportFactory::class,
        Bridge\MessageBird\MessageBirdTransportFactory::class,
        Bridge\MessageMedia\MessageMediaTransportFactory::class,
        Bridge\MicrosoftTeams\MicrosoftTeamsTransportFactory::class,
        Bridge\Mobyt\MobytTransportFactory::class,
        Bridge\Novu\NovuTransportFactory::class,
        Bridge\Ntfy\NtfyTransportFactory::class,
        Bridge\Octopush\OctopushTransportFactory::class,
        Bridge\OneSignal\OneSignalTransportFactory::class,
        Bridge\OrangeSms\OrangeSmsTransportFactory::class,
        Bridge\OvhCloud\OvhCloudTransportFactory::class,
        Bridge\PagerDuty\PagerDutyTransportFactory::class,
        Bridge\Plivo\PlivoTransportFactory::class,
        Bridge\Primotexto\PrimotextoTransportFactory::class,
        Bridge\Pushover\PushoverTransportFactory::class,
        Bridge\Pushy\PushyTransportFactory::class,
        Bridge\Redlink\RedlinkTransportFactory::class,
        Bridge\RingCentral\RingCentralTransportFactory::class,
        Bridge\RocketChat\RocketChatTransportFactory::class,
        Bridge\Sendberry\SendberryTransportFactory::class,
        Bridge\Sevenio\SevenIoTransportFactory::class,
        Bridge\Sipgate\SipgateTransportFactory::class,
        Bridge\SimpleTextin\SimpleTextinTransportFactory::class,
        Bridge\Sinch\SinchTransportFactory::class,
        Bridge\Slack\SlackTransportFactory::class,
        Bridge\Sms77\Sms77TransportFactory::class,
        Bridge\Smsapi\SmsapiTransportFactory::class,
        Bridge\SmsBiuras\SmsBiurasTransportFactory::class,
        Bridge\Smsbox\SmsboxTransportFactory::class,
        Bridge\Smsc\SmscTransportFactory::class,
        Bridge\Smsense\SmsenseTransportFactory::class,
        Bridge\SmsFactor\SmsFactorTransportFactory::class,
        Bridge\Smsmode\SmsmodeTransportFactory::class,
        Bridge\SmsSluzba\SmsSluzbaTransportFactory::class,
        Bridge\SpotHit\SpotHitTransportFactory::class,
        Bridge\Sweego\SweegoTransportFactory::class,
        Bridge\Telegram\TelegramTransportFactory::class,
        Bridge\Telnyx\TelnyxTransportFactory::class,
        Bridge\Termii\TermiiTransportFactory::class,
        Bridge\TurboSms\TurboSmsTransportFactory::class,
        Bridge\Twilio\TwilioTransportFactory::class,
        Bridge\Twitter\TwitterTransportFactory::class,
        Bridge\Unifonic\UnifonicTransportFactory::class,
        Bridge\Vonage\VonageTransportFactory::class,
        Bridge\Yunpian\YunpianTransportFactory::class,
        Bridge\Zendesk\ZendeskTransportFactory::class,
        Bridge\Zulip\ZulipTransportFactory::class,
    ];

    public static function fromDsn(#[\SensitiveParameter] string $dsn, ?EventDispatcherInterface $dispatcher = null, ?HttpClientInterface $client = null): TransportInterface
    {
        $factory = new self(self::getDefaultFactories($dispatcher, $client));

        return $factory->fromString($dsn);
    }

    public static function fromDsns(#[\SensitiveParameter] array $dsns, ?EventDispatcherInterface $dispatcher = null, ?HttpClientInterface $client = null): TransportInterface
    {
        $factory = new self(iterator_to_array(self::getDefaultFactories($dispatcher, $client)));

        return $factory->fromStrings($dsns);
    }

    /**
     * @param iterable<mixed, TransportFactoryInterface> $factories
     */
    public function __construct(
        private iterable $factories,
    ) {
    }

    public function fromStrings(#[\SensitiveParameter] array $dsns): Transports
    {
        $transports = [];
        foreach ($dsns as $name => $dsn) {
            $transports[$name] = $this->fromString($dsn);
        }

        return new Transports($transports);
    }

    public function fromString(#[\SensitiveParameter] string $dsn): TransportInterface
    {
        $dsns = preg_split('/\s++\|\|\s++/', $dsn);
        if (\count($dsns) > 1) {
            return new FailoverTransport($this->createFromDsns($dsns));
        }

        $dsns = preg_split('/\s++&&\s++/', $dsn);
        if (\count($dsns) > 1) {
            return new RoundRobinTransport($this->createFromDsns($dsns));
        }

        return $this->fromDsnObject(new Dsn($dsn));
    }

    public function fromDsnObject(Dsn $dsn): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn);
            }
        }

        throw new UnsupportedSchemeException($dsn);
    }

    /**
     * @return TransportInterface[]
     */
    private function createFromDsns(#[\SensitiveParameter] array $dsns): array
    {
        $transports = [];
        foreach ($dsns as $dsn) {
            $transports[] = $this->fromDsnObject(new Dsn($dsn));
        }

        return $transports;
    }

    /**
     * @return TransportFactoryInterface[]
     */
    private static function getDefaultFactories(?EventDispatcherInterface $dispatcher = null, ?HttpClientInterface $client = null): iterable
    {
        foreach (self::FACTORY_CLASSES as $factoryClass) {
            if (class_exists($factoryClass)) {
                yield new $factoryClass($dispatcher, $client);
            }
        }

        yield new NullTransportFactory($dispatcher, $client);
    }
}
