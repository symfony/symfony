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
use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsTransportFactory;
use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsTransportFactory;
use Symfony\Component\Notifier\Bridge\Clickatell\ClickatellTransportFactory;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransportFactory;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransportFactory;
use Symfony\Component\Notifier\Bridge\FortySixElks\FortySixElksTransportFactory;
use Symfony\Component\Notifier\Bridge\FreeMobile\FreeMobileTransportFactory;
use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiTransportFactory;
use Symfony\Component\Notifier\Bridge\Gitter\GitterTransportFactory;
use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatTransportFactory;
use Symfony\Component\Notifier\Bridge\Infobip\InfobipTransportFactory;
use Symfony\Component\Notifier\Bridge\Iqsms\IqsmsTransportFactory;
use Symfony\Component\Notifier\Bridge\LightSms\LightSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\LinkedIn\LinkedInTransportFactory;
use Symfony\Component\Notifier\Bridge\Mailjet\MailjetTransportFactory;
use Symfony\Component\Notifier\Bridge\Mattermost\MattermostTransportFactory;
use Symfony\Component\Notifier\Bridge\Mercure\MercureTransportFactory;
use Symfony\Component\Notifier\Bridge\MessageBird\MessageBirdTransportFactory;
use Symfony\Component\Notifier\Bridge\MessageMedia\MessageMediaTransportFactory;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsTransportFactory;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytTransportFactory;
use Symfony\Component\Notifier\Bridge\Octopush\OctopushTransportFactory;
use Symfony\Component\Notifier\Bridge\OneSignal\OneSignalTransportFactory;
use Symfony\Component\Notifier\Bridge\OvhCloud\OvhCloudTransportFactory;
use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransportFactory;
use Symfony\Component\Notifier\Bridge\Sendberry\SendberryTransportFactory;
use Symfony\Component\Notifier\Bridge\Sendinblue\SendinblueTransportFactory;
use Symfony\Component\Notifier\Bridge\Sinch\SinchTransportFactory;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;
use Symfony\Component\Notifier\Bridge\Sms77\Sms77TransportFactory;
use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransportFactory;
use Symfony\Component\Notifier\Bridge\SmsBiuras\SmsBiurasTransportFactory;
use Symfony\Component\Notifier\Bridge\Smsc\SmscTransportFactory;
use Symfony\Component\Notifier\Bridge\SpotHit\SpotHitTransportFactory;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransportFactory;
use Symfony\Component\Notifier\Bridge\Telnyx\TelnyxTransportFactory;
use Symfony\Component\Notifier\Bridge\TurboSms\TurboSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory;
use Symfony\Component\Notifier\Bridge\Vonage\VonageTransportFactory;
use Symfony\Component\Notifier\Bridge\Yunpian\YunpianTransportFactory;
use Symfony\Component\Notifier\Bridge\Zulip\ZulipTransportFactory;
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
            AllMySmsTransportFactory::class => false,
            AmazonSnsTransportFactory::class => false,
            ClickatellTransportFactory::class => false,
            DiscordTransportFactory::class => false,
            EsendexTransportFactory::class => false,
            FakeChatTransportFactory::class => false,
            FakeSmsTransportFactory::class => false,
            FirebaseTransportFactory::class => false,
            FortySixElksTransportFactory::class => false,
            FreeMobileTransportFactory::class => false,
            GatewayApiTransportFactory::class => false,
            GitterTransportFactory::class => false,
            GoogleChatTransportFactory::class => false,
            InfobipTransportFactory::class => false,
            IqsmsTransportFactory::class => false,
            LightSmsTransportFactory::class => false,
            LinkedInTransportFactory::class => false,
            MailjetTransportFactory::class => false,
            MattermostTransportFactory::class => false,
            MercureTransportFactory::class => false,
            MessageBirdTransportFactory::class => false,
            MessageMediaTransportFactory::class => false,
            MicrosoftTeamsTransportFactory::class => false,
            MobytTransportFactory::class => false,
            OctopushTransportFactory::class => false,
            OneSignalTransportFactory::class => false,
            OvhCloudTransportFactory::class => false,
            RocketChatTransportFactory::class => false,
            SendberryTransportFactory::class => false,
            SendinblueTransportFactory::class => false,
            SinchTransportFactory::class => false,
            SlackTransportFactory::class => false,
            Sms77TransportFactory::class => false,
            SmsapiTransportFactory::class => false,
            SmsBiurasTransportFactory::class => false,
            SmscTransportFactory::class => false,
            SpotHitTransportFactory::class => false,
            TelegramTransportFactory::class => false,
            TelnyxTransportFactory::class => false,
            TurboSmsTransportFactory::class => false,
            TwilioTransportFactory::class => false,
            VonageTransportFactory::class => false,
            YunpianTransportFactory::class => false,
            ZulipTransportFactory::class => false,
        ]);
    }

    /**
     * @dataProvider messageWhereSchemeIsPartOfSchemeToPackageMapProvider
     */
    public function testMessageWhereSchemeIsPartOfSchemeToPackageMap(string $scheme, string $package)
    {
        $dsn = new Dsn(sprintf('%s://localhost', $scheme));

        $this->assertSame(
            sprintf('Unable to send notification via "%s" as the bridge is not installed; try running "composer require %s".', $scheme, $package),
            (new UnsupportedSchemeException($dsn))->getMessage()
        );
    }

    public function messageWhereSchemeIsPartOfSchemeToPackageMapProvider(): \Generator
    {
        yield ['allmysms', 'symfony/all-my-sms-notifier'];
        yield ['sns', 'symfony/amazon-sns-notifier'];
        yield ['clickatell', 'symfony/clickatell-notifier'];
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
        yield ['lightsms', 'symfony/light-sms-notifier'];
        yield ['linkedin', 'symfony/linked-in-notifier'];
        yield ['mailjet', 'symfony/mailjet-notifier'];
        yield ['mattermost', 'symfony/mattermost-notifier'];
        yield ['mercure', 'symfony/mercure-notifier'];
        yield ['messagebird', 'symfony/message-bird-notifier'];
        yield ['messagemedia', 'symfony/message-media-notifier'];
        yield ['microsoftteams', 'symfony/microsoft-teams-notifier'];
        yield ['mobyt', 'symfony/mobyt-notifier'];
        yield ['octopush', 'symfony/octopush-notifier'];
        yield ['onesignal', 'symfony/one-signal-notifier'];
        yield ['ovhcloud', 'symfony/ovh-cloud-notifier'];
        yield ['rocketchat', 'symfony/rocket-chat-notifier'];
        yield ['sendberry', 'symfony/sendberry-notifier'];
        yield ['sendinblue', 'symfony/sendinblue-notifier'];
        yield ['sinch', 'symfony/sinch-notifier'];
        yield ['slack', 'symfony/slack-notifier'];
        yield ['sms77', 'symfony/sms77-notifier'];
        yield ['smsapi', 'symfony/smsapi-notifier'];
        yield ['smsbiuras', 'symfony/sms-biuras-notifier'];
        yield ['smsc', 'symfony/smsc-notifier'];
        yield ['spothit', 'symfony/spot-hit-notifier'];
        yield ['telegram', 'symfony/telegram-notifier'];
        yield ['telnyx', 'symfony/telnyx-notifier'];
        yield ['turbosms', 'symfony/turbo-sms-notifier'];
        yield ['twilio', 'symfony/twilio-notifier'];
        yield ['zulip', 'symfony/zulip-notifier'];
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

    public function messageWhereSchemeIsNotPartOfSchemeToPackageMapProvider(): \Generator
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
