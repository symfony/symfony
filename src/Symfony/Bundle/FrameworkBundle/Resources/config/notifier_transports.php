<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Notifier\Bridge;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\NullTransportFactory;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('notifier.transport_factory.abstract', AbstractTransportFactory::class)
            ->abstract()
            ->args([
                service('event_dispatcher'),
                service('http_client')->ignoreOnInvalid(),
            ]);

    $chatterFactories = [
        'google-chat' => Bridge\GoogleChat\GoogleChatTransportFactory::class,
        'telegram' => Bridge\Telegram\TelegramTransportFactory::class,
        'bluesky' => Bridge\Bluesky\BlueskyTransportFactory::class,
        'fake-chat' => Bridge\FakeChat\FakeChatTransportFactory::class,
        'firebase' => Bridge\Firebase\FirebaseTransportFactory::class,
        'gitter' => Bridge\Gitter\GitterTransportFactory::class,
        'line-notify' => Bridge\LineNotify\LineNotifyTransportFactory::class,
        'linked-in' => Bridge\LinkedIn\LinkedInTransportFactory::class,
        'mastodon' => Bridge\Mastodon\MastodonTransportFactory::class,
        'mercure' => Bridge\Mercure\MercureTransportFactory::class,
        'microsoft-teams' => Bridge\MicrosoftTeams\MicrosoftTeamsTransportFactory::class,
        'pager-duty' => Bridge\PagerDuty\PagerDutyTransportFactory::class,
        'rocket-chat' => Bridge\RocketChat\RocketChatTransportFactory::class,
        'twitter' => Bridge\Twitter\TwitterTransportFactory::class,
        'zulip' => Bridge\Zulip\ZulipTransportFactory::class,
        'brevo' => Bridge\Brevo\BrevoTransportFactory::class,
        'chatwork' => Bridge\Chatwork\ChatworkTransportFactory::class,
        'discord' => Bridge\Discord\DiscordTransportFactory::class,
        'mattermost' => Bridge\Mattermost\MattermostTransportFactory::class,
        'slack' => Bridge\Slack\SlackTransportFactory::class,
        'zendesk' => Bridge\Zendesk\ZendeskTransportFactory::class,
    ];

    foreach ($chatterFactories as $name => $class) {
        $container->services()
            ->set('notifier.transport_factory.'.$name, $class)
                ->parent('notifier.transport_factory.abstract')
                ->tag('chatter.transport_factory');
    }

    $texterFactories = [
        'all-my-sms' => Bridge\AllMySms\AllMySmsTransportFactory::class,
        'bandwidth' => Bridge\Bandwidth\BandwidthTransportFactory::class,
        'click-send' => Bridge\ClickSend\ClickSendTransportFactory::class,
        'clickatell' => Bridge\Clickatell\ClickatellTransportFactory::class,
        'contact-everyone' => Bridge\ContactEveryone\ContactEveryoneTransportFactory::class,
        'engagespot' => Bridge\Engagespot\EngagespotTransportFactory::class,
        'esendex' => Bridge\Esendex\EsendexTransportFactory::class,
        'expo' => Bridge\Expo\ExpoTransportFactory::class,
        'fake-sms' => Bridge\FakeSms\FakeSmsTransportFactory::class,
        'forty-six-elks' => Bridge\FortySixElks\FortySixElksTransportFactory::class,
        'free-mobile' => Bridge\FreeMobile\FreeMobileTransportFactory::class,
        'gateway-api' => Bridge\GatewayApi\GatewayApiTransportFactory::class,
        'go-ip' => Bridge\GoIp\GoIpTransportFactory::class,
        'infobip' => Bridge\Infobip\InfobipTransportFactory::class,
        'iqsms' => Bridge\Iqsms\IqsmsTransportFactory::class,
        'isendpro' => Bridge\Isendpro\IsendproTransportFactory::class,
        'kaz-info-teh' => Bridge\KazInfoTeh\KazInfoTehTransportFactory::class,
        'mailjet' => Bridge\Mailjet\MailjetTransportFactory::class,
        'message-bird' => Bridge\MessageBird\MessageBirdTransportFactory::class,
        'message-media' => Bridge\MessageMedia\MessageMediaTransportFactory::class,
        'mobyt' => Bridge\Mobyt\MobytTransportFactory::class,
        'novu' => Bridge\Novu\NovuTransportFactory::class,
        'ntfy' => Bridge\Ntfy\NtfyTransportFactory::class,
        'octopush' => Bridge\Octopush\OctopushTransportFactory::class,
        'one-signal' => Bridge\OneSignal\OneSignalTransportFactory::class,
        'orange-sms' => Bridge\OrangeSms\OrangeSmsTransportFactory::class,
        'ovh-cloud' => Bridge\OvhCloud\OvhCloudTransportFactory::class,
        'plivo' => Bridge\Plivo\PlivoTransportFactory::class,
        'pushover' => Bridge\Pushover\PushoverTransportFactory::class,
        'redlink' => Bridge\Redlink\RedlinkTransportFactory::class,
        'ring-central' => Bridge\RingCentral\RingCentralTransportFactory::class,
        'sendberry' => Bridge\Sendberry\SendberryTransportFactory::class,
        'simple-textin' => Bridge\SimpleTextin\SimpleTextinTransportFactory::class,
        'sinch' => Bridge\Sinch\SinchTransportFactory::class,
        'sms-factor' => Bridge\SmsFactor\SmsFactorTransportFactory::class,
        'sms77' => Bridge\Sms77\Sms77TransportFactory::class,
        'smsapi' => Bridge\Smsapi\SmsapiTransportFactory::class,
        'smsc' => Bridge\Smsc\SmscTransportFactory::class,
        'smsmode' => Bridge\Smsmode\SmsmodeTransportFactory::class,
        'spot-hit' => Bridge\SpotHit\SpotHitTransportFactory::class,
        'telnyx' => Bridge\Telnyx\TelnyxTransportFactory::class,
        'termii' => Bridge\Termii\TermiiTransportFactory::class,
        'turbo-sms' => Bridge\TurboSms\TurboSmsTransportFactory::class,
        'twilio' => Bridge\Twilio\TwilioTransportFactory::class,
        'unifonic' => Bridge\Unifonic\UnifonicTransportFactory::class,
        'vonage' => Bridge\Vonage\VonageTransportFactory::class,
        'yunpian' => Bridge\Yunpian\YunpianTransportFactory::class,
        'light-sms' => Bridge\LightSms\LightSmsTransportFactory::class,
        'sms-biuras' => Bridge\SmsBiuras\SmsBiurasTransportFactory::class,
        'smsbox' => Bridge\Smsbox\SmsboxTransportFactory::class,
    ];

    foreach ($texterFactories as $name => $class) {
        $container->services()
            ->set('notifier.transport_factory.'.$name, $class)
                ->parent('notifier.transport_factory.abstract')
                ->tag('texter.transport_factory');
    }

    $container->services()
        ->set('notifier.transport_factory.amazon-sns', Bridge\AmazonSns\AmazonSnsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.null', NullTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')
            ->tag('texter.transport_factory')
    ;
};
