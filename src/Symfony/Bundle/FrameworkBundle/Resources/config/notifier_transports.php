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
            ->args([service('event_dispatcher'), service('http_client')->ignoreOnInvalid()])

        ->set('notifier.transport_factory.slack', Bridge\Slack\SlackTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.linked-in', Bridge\LinkedIn\LinkedInTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.telegram', Bridge\Telegram\TelegramTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.mattermost', Bridge\Mattermost\MattermostTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.vonage', Bridge\Vonage\VonageTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.rocket-chat', Bridge\RocketChat\RocketChatTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.google-chat', Bridge\GoogleChat\GoogleChatTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.twilio', Bridge\Twilio\TwilioTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.twitter', Bridge\Twitter\TwitterTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.all-my-sms', Bridge\AllMySms\AllMySmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.firebase', Bridge\Firebase\FirebaseTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.forty-six-elks', Bridge\FortySixElks\FortySixElksTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.free-mobile', Bridge\FreeMobile\FreeMobileTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.spot-hit', Bridge\SpotHit\SpotHitTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.fake-chat', Bridge\FakeChat\FakeChatTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.fake-sms', Bridge\FakeSms\FakeSmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.ovh-cloud', Bridge\OvhCloud\OvhCloudTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.sinch', Bridge\Sinch\SinchTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.zulip', Bridge\Zulip\ZulipTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.infobip', Bridge\Infobip\InfobipTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.isendpro', Bridge\Isendpro\IsendproTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.mobyt', Bridge\Mobyt\MobytTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.smsapi', Bridge\Smsapi\SmsapiTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.esendex', Bridge\Esendex\EsendexTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.sendberry', Bridge\Sendberry\SendberryTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.sendinblue', Bridge\Sendinblue\SendinblueTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.iqsms', Bridge\Iqsms\IqsmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.octopush', Bridge\Octopush\OctopushTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.discord', Bridge\Discord\DiscordTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.microsoft-teams', Bridge\MicrosoftTeams\MicrosoftTeamsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.gateway-api', Bridge\GatewayApi\GatewayApiTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.mercure', Bridge\Mercure\MercureTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.gitter', Bridge\Gitter\GitterTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.clickatell', Bridge\Clickatell\ClickatellTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.contact-everyone', Bridge\ContactEveryone\ContactEveryoneTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.amazon-sns', Bridge\AmazonSns\AmazonSnsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.null', NullTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.light-sms', Bridge\LightSms\LightSmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.sms-biuras', Bridge\SmsBiuras\SmsBiurasTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.smsc', Bridge\Smsc\SmscTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.sms-factor', Bridge\SmsFactor\SmsFactorTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.message-bird', Bridge\MessageBird\MessageBirdTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.message-media', Bridge\MessageMedia\MessageMediaTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.telnyx', Bridge\Telnyx\TelnyxTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.mailjet', Bridge\Mailjet\MailjetTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.yunpian', Bridge\Yunpian\YunpianTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.turbo-sms', Bridge\TurboSms\TurboSmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.sms77', Bridge\Sms77\Sms77TransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.one-signal', Bridge\OneSignal\OneSignalTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.orange-sms', Bridge\OrangeSms\OrangeSmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.expo', Bridge\Expo\ExpoTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.kaz-info-teh', Bridge\KazInfoTeh\KazInfoTehTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.engagespot', Bridge\Engagespot\EngagespotTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.zendesk', Bridge\Zendesk\ZendeskTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.chatwork', Bridge\Chatwork\ChatworkTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.termii', Bridge\Termii\TermiiTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.ring-central', Bridge\RingCentral\RingCentralTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.plivo', Bridge\Plivo\PlivoTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.bandwidth', Bridge\Bandwidth\BandwidthTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.line-notify', Bridge\LineNotify\LineNotifyTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.mastodon', Bridge\Mastodon\MastodonTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.pager-duty', Bridge\PagerDuty\PagerDutyTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.pushover', Bridge\Pushover\PushoverTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.simple-textin', Bridge\SimpleTextin\SimpleTextinTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')
    ;
};
