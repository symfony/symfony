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
use Symfony\Component\Notifier\Bridge\Clickatell\ClickatellTransportFactory;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransportFactory;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransportFactory;
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
use Symfony\Component\Notifier\Bridge\Mattermost\MattermostTransportFactory;
use Symfony\Component\Notifier\Bridge\Mercure\MercureTransportFactory;
use Symfony\Component\Notifier\Bridge\MessageBird\MessageBirdTransportFactory;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsTransportFactory;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytTransportFactory;
use Symfony\Component\Notifier\Bridge\Nexmo\NexmoTransportFactory;
use Symfony\Component\Notifier\Bridge\Octopush\OctopushTransportFactory;
use Symfony\Component\Notifier\Bridge\OvhCloud\OvhCloudTransportFactory;
use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransportFactory;
use Symfony\Component\Notifier\Bridge\Sendinblue\SendinblueTransportFactory;
use Symfony\Component\Notifier\Bridge\Sinch\SinchTransportFactory;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;
use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransportFactory;
use Symfony\Component\Notifier\Bridge\SmsBiuras\SmsBiurasTransportFactory;
use Symfony\Component\Notifier\Bridge\SpotHit\SpotHitTransportFactory;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransportFactory;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory;
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

        ->set('notifier.transport_factory.linkedin', LinkedInTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.telegram', TelegramTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.mattermost', MattermostTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.nexmo', NexmoTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.rocketchat', RocketChatTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.googlechat', GoogleChatTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.twilio', TwilioTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.allmysms', AllMySmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.firebase', FirebaseTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.freemobile', FreeMobileTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.spothit', SpotHitTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.fakechat', FakeChatTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.fakesms', FakeSmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.ovhcloud', OvhCloudTransportFactory::class)
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

        ->set('notifier.transport_factory.microsoftteams', MicrosoftTeamsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')

        ->set('notifier.transport_factory.gatewayapi', GatewayApiTransportFactory::class)
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

        ->set('notifier.transport_factory.null', NullTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('chatter.transport_factory')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.lightsms', LightSmsTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.smsbiuras', SmsBiurasTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')

        ->set('notifier.transport_factory.messagebird', MessageBirdTransportFactory::class)
            ->parent('notifier.transport_factory.abstract')
            ->tag('texter.transport_factory')
    ;
};
