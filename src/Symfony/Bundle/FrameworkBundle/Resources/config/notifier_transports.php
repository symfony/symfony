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

use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsTransportFactory;
use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsTransportFactory;
use Symfony\Component\Notifier\Bridge\Clickatell\ClickatellTransportFactory;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransportFactory;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransportFactory;
use Symfony\Component\Notifier\Bridge\Expo\ExpoTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransportFactory;
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
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\NullTransportFactory;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('notifier.transport_factory.abstract', AbstractTransportFactory::class)
            ->abstract()
            ->args([service('event_dispatcher'), service('http_client')->ignoreOnInvalid()])

        ->set('notifier.transport_factory.slack', SlackTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.linked-in', LinkedInTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.telegram', TelegramTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.mattermost', MattermostTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.vonage', VonageTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.rocket-chat', RocketChatTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.google-chat', GoogleChatTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.twilio', TwilioTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.all-my-sms', AllMySmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.firebase', FirebaseTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.free-mobile', FreeMobileTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.spot-hit', SpotHitTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.fake-chat', FakeChatTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.fake-sms', FakeSmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.ovh-cloud', OvhCloudTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.sinch', SinchTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.zulip', ZulipTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.infobip', InfobipTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.mobyt', MobytTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.smsapi', SmsapiTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.esendex', EsendexTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.sendinblue', SendinblueTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.iqsms', IqsmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.octopush', OctopushTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.discord', DiscordTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.microsoft-teams', MicrosoftTeamsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.gateway-api', GatewayApiTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.mercure', MercureTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.gitter', GitterTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.clickatell', ClickatellTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.amazon-sns', AmazonSnsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.null', NullTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.light-sms', LightSmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.sms-biuras', SmsBiurasTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.smsc', SmscTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.message-bird', MessageBirdTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.message-media', MessageMediaTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.telnyx', TelnyxTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.mailjet', MailjetTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.yunpian', YunpianTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.turbo-sms', TurboSmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.sms77', Sms77TransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.one-signal', OneSignalTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.expo', ExpoTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')
    ;
};
