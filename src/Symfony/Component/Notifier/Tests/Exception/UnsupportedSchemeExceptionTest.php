<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClassExistsMock;
use Symfony\Component\Notifier\Bridge;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @runTestsInSeparateProcesses
 */
final class UnsupportedSchemeExceptionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClassExistsMock::register(__CLASS__);
        ClassExistsMock::withMockedClasses([
            Bridge\AllMySms\AllMySmsTransportFactory::class => false,
            Bridge\AmazonSns\AmazonSnsTransportFactory::class => false,
            Bridge\Bandwidth\BandwidthTransportFactory::class => false,
            Bridge\Brevo\BrevoTransportFactory::class => false,
            Bridge\Chatwork\ChatworkTransportFactory::class => false,
            Bridge\Clickatell\ClickatellTransportFactory::class => false,
            Bridge\ClickSend\ClickSendTransportFactory::class => false,
            Bridge\ContactEveryone\ContactEveryoneTransportFactory::class => false,
            Bridge\Discord\DiscordTransportFactory::class => false,
            Bridge\Engagespot\EngagespotTransportFactory::class => false,
            Bridge\Esendex\EsendexTransportFactory::class => false,
            Bridge\Expo\ExpoTransportFactory::class => false,
            Bridge\FakeChat\FakeChatTransportFactory::class => false,
            Bridge\FakeSms\FakeSmsTransportFactory::class => false,
            Bridge\Firebase\FirebaseTransportFactory::class => false,
            Bridge\FortySixElks\FortySixElksTransportFactory::class => false,
            Bridge\FreeMobile\FreeMobileTransportFactory::class => false,
            Bridge\GatewayApi\GatewayApiTransportFactory::class => false,
            Bridge\Gitter\GitterTransportFactory::class => false,
            Bridge\GoIp\GoIpTransportFactory::class => false,
            Bridge\GoogleChat\GoogleChatTransportFactory::class => false,
            Bridge\Infobip\InfobipTransportFactory::class => false,
            Bridge\Iqsms\IqsmsTransportFactory::class => false,
            Bridge\Isendpro\IsendproTransportFactory::class => false,
            Bridge\KazInfoTeh\KazInfoTehTransportFactory::class => false,
            Bridge\LightSms\LightSmsTransportFactory::class => false,
            Bridge\LineNotify\LineNotifyTransportFactory::class => false,
            Bridge\LinkedIn\LinkedInTransportFactory::class => false,
            Bridge\Mailjet\MailjetTransportFactory::class => false,
            Bridge\Mastodon\MastodonTransportFactory::class => false,
            Bridge\Mattermost\MattermostTransportFactory::class => false,
            Bridge\Mercure\MercureTransportFactory::class => false,
            Bridge\MessageBird\MessageBirdTransportFactory::class => false,
            Bridge\MessageMedia\MessageMediaTransportFactory::class => false,
            Bridge\MicrosoftTeams\MicrosoftTeamsTransportFactory::class => false,
            Bridge\Mobyt\MobytTransportFactory::class => false,
            Bridge\Novu\NovuTransportFactory::class => false,
            Bridge\Ntfy\NtfyTransportFactory::class => false,
            Bridge\Octopush\OctopushTransportFactory::class => false,
            Bridge\OneSignal\OneSignalTransportFactory::class => false,
            Bridge\OrangeSms\OrangeSmsTransportFactory::class => false,
            Bridge\OvhCloud\OvhCloudTransportFactory::class => false,
            Bridge\PagerDuty\PagerDutyTransportFactory::class => false,
            Bridge\Plivo\PlivoTransportFactory::class => false,
            Bridge\Pushover\PushoverTransportFactory::class => false,
            Bridge\RingCentral\RingCentralTransportFactory::class => false,
            Bridge\Redlink\RedlinkTransportFactory::class => false,
            Bridge\RocketChat\RocketChatTransportFactory::class => false,
            Bridge\Sendberry\SendberryTransportFactory::class => false,
            Bridge\Sendinblue\SendinblueTransportFactory::class => false,
            Bridge\SimpleTextin\SimpleTextinTransportFactory::class => false,
            Bridge\Sinch\SinchTransportFactory::class => false,
            Bridge\Slack\SlackTransportFactory::class => false,
            Bridge\Sms77\Sms77TransportFactory::class => false,
            Bridge\Smsapi\SmsapiTransportFactory::class => false,
            Bridge\SmsBiuras\SmsBiurasTransportFactory::class => false,
            Bridge\Smsc\SmscTransportFactory::class => false,
            Bridge\SmsFactor\SmsFactorTransportFactory::class => false,
            Bridge\Smsmode\SmsmodeTransportFactory::class => false,
            Bridge\SpotHit\SpotHitTransportFactory::class => false,
            Bridge\Telegram\TelegramTransportFactory::class => false,
            Bridge\Telnyx\TelnyxTransportFactory::class => false,
            Bridge\Termii\TermiiTransportFactory::class => false,
            Bridge\TurboSms\TurboSmsTransportFactory::class => false,
            Bridge\Twilio\TwilioTransportFactory::class => false,
            Bridge\Twitter\TwitterTransportFactory::class => false,
            Bridge\Vonage\VonageTransportFactory::class => false,
            Bridge\Yunpian\YunpianTransportFactory::class => false,
            Bridge\Zendesk\ZendeskTransportFactory::class => false,
            Bridge\Zulip\ZulipTransportFactory::class => false,
        ]);
    }

    /**
     * @dataProvider messageWhereSchemeIsPartOfSchemeToPackageMapProvider
     */
    public function testMessageWhereSchemeIsPartOfSchemeToPackageMap(string $scheme, string $package)
    {
        $dsn = new Dsn(sprintf('%s://localhost', $scheme));

        $this->assertSame(
            sprintf('Unable to send notification via "%s" as the bridge is not installed. Try running "composer require %s".', $scheme, $package),
            (new UnsupportedSchemeException($dsn))->getMessage()
        );
    }

    public static function messageWhereSchemeIsPartOfSchemeToPackageMapProvider(): \Generator
    {
        yield ['allmysms', 'symfony/all-my-sms-notifier'];
        yield ['sns', 'symfony/amazon-sns-notifier'];
        yield ['bandwidth', 'symfony/bandwidth-notifier'];
        yield ['brevo', 'symfony/brevo-notifier'];
        yield ['clickatell', 'symfony/clickatell-notifier'];
        yield ['clicksend', 'symfony/click-send-notifier'];
        yield ['contact-everyone', 'symfony/contact-everyone-notifier'];
        yield ['discord', 'symfony/discord-notifier'];
        yield ['esendex', 'symfony/esendex-notifier'];
        yield ['fakechat', 'symfony/fake-chat-notifier'];
        yield ['fakesms', 'symfony/fake-sms-notifier'];
        yield ['firebase', 'symfony/firebase-notifier'];
        yield ['freemobile', 'symfony/free-mobile-notifier'];
        yield ['gatewayapi', 'symfony/gateway-api-notifier'];
        yield ['gitter', 'symfony/gitter-notifier'];
        yield ['googlechat', 'symfony/google-chat-notifier'];
        yield ['infobip', 'symfony/infobip-notifier'];
        yield ['iqsms', 'symfony/iqsms-notifier'];
        yield ['isendpro', 'symfony/isendpro-notifier'];
        yield ['lightsms', 'symfony/light-sms-notifier'];
        yield ['linenotify', 'symfony/line-notify-notifier'];
        yield ['linkedin', 'symfony/linked-in-notifier'];
        yield ['mailjet', 'symfony/mailjet-notifier'];
        yield ['mastodon', 'symfony/mastodon-notifier'];
        yield ['mattermost', 'symfony/mattermost-notifier'];
        yield ['mercure', 'symfony/mercure-notifier'];
        yield ['messagebird', 'symfony/message-bird-notifier'];
        yield ['messagemedia', 'symfony/message-media-notifier'];
        yield ['microsoftteams', 'symfony/microsoft-teams-notifier'];
        yield ['mobyt', 'symfony/mobyt-notifier'];
        yield ['novu', 'symfony/novu-notifier'];
        yield ['ntfy', 'symfony/ntfy-notifier'];
        yield ['octopush', 'symfony/octopush-notifier'];
        yield ['onesignal', 'symfony/one-signal-notifier'];
        yield ['ovhcloud', 'symfony/ovh-cloud-notifier'];
        yield ['plivo', 'symfony/plivo-notifier'];
        yield ['redlink', 'symfony/redlink-notifier'];
        yield ['ringcentral', 'symfony/ring-central-notifier'];
        yield ['rocketchat', 'symfony/rocket-chat-notifier'];
        yield ['sendberry', 'symfony/sendberry-notifier'];
        yield ['sendinblue', 'symfony/sendinblue-notifier'];
        yield ['simpletextin', 'symfony/simple-textin-notifier'];
        yield ['sinch', 'symfony/sinch-notifier'];
        yield ['slack', 'symfony/slack-notifier'];
        yield ['sms77', 'symfony/sms77-notifier'];
        yield ['smsapi', 'symfony/smsapi-notifier'];
        yield ['smsbiuras', 'symfony/sms-biuras-notifier'];
        yield ['smsc', 'symfony/smsc-notifier'];
        yield ['sms-factor', 'symfony/sms-factor-notifier'];
        yield ['smsmode', 'symfony/smsmode-notifier'];
        yield ['spothit', 'symfony/spot-hit-notifier'];
        yield ['telegram', 'symfony/telegram-notifier'];
        yield ['telnyx', 'symfony/telnyx-notifier'];
        yield ['termii', 'symfony/termii-notifier'];
        yield ['turbosms', 'symfony/turbo-sms-notifier'];
        yield ['twilio', 'symfony/twilio-notifier'];
        yield ['twitter', 'symfony/twitter-notifier'];
        yield ['zendesk', 'symfony/zendesk-notifier'];
        yield ['zulip', 'symfony/zulip-notifier'];
        yield ['goip', 'symfony/go-ip-notifier'];
    }

    /**
     * @dataProvider messageWhereSchemeIsNotPartOfSchemeToPackageMapProvider
     */
    public function testMessageWhereSchemeIsNotPartOfSchemeToPackageMap(string $expected, Dsn $dsn, ?string $name, array $supported)
    {
        $this->assertSame(
            $expected,
            (new UnsupportedSchemeException($dsn, $name, $supported))->getMessage()
        );
    }

    public static function messageWhereSchemeIsNotPartOfSchemeToPackageMapProvider(): \Generator
    {
        yield [
            'The "somethingElse" scheme is not supported.',
            new Dsn('somethingElse://localhost'),
            null,
            [],
        ];

        yield [
            'The "somethingElse" scheme is not supported.',
            new Dsn('somethingElse://localhost'),
            'foo',
            [],
        ];

        yield [
            'The "somethingElse" scheme is not supported; supported schemes for notifier "one" are: "one", "two".',
            new Dsn('somethingElse://localhost'),
            'one',
            ['one', 'two'],
        ];
    }
}
