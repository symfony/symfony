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

use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\Notifier\Bridge as NotifierBridge;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsTransportFactory;
use Symfony\Component\Notifier\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface as NotifierTransportFactoryInterface;
use Symfony\Component\Webhook\Controller\WebhookController;

/**
 * Provides the services that send notifications.
 */
#[RequiredBundle(ServicesBundle::class)]
#[RequiredBundle(MessengerBundle::class, ignoreOnInvalid: true)]
class NotifierBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveMissingDependenciesPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->children()
                ->scalarNode('message_bus')->defaultNull()->info('The message bus to use. Defaults to the default bus if the Messenger component is installed.')->end()
                ->arrayNode('chatter_transports', 'chatter_transport')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('texter_transports', 'texter_transport')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
                ->booleanNode('notification_on_failed_messages')->defaultFalse()->end()
                ->arrayNode('channel_policy')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->acceptAndWrap(['string'])
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
                ->arrayNode('admin_recipients', 'admin_recipient')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('email')->cannotBeEmpty()->end()
                            ->scalarNode('phone')->defaultValue('')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/notifier.php');
        $configurator->import('Resources/config/notifier_transports.php');

        if ($container->getParameter('kernel.debug')) {
            $configurator->import('Resources/config/notifier_debug.php');
        }

        if ($config['chatter_transports']) {
            $container->getDefinition('chatter.transports')->setArgument(0, $config['chatter_transports']);
        } else {
            $container->removeDefinition('chatter');
            $container->removeAlias(ChatterInterface::class);
        }
        if ($config['texter_transports']) {
            $container->getDefinition('texter.transports')->setArgument(0, $config['texter_transports']);
        } else {
            $container->removeDefinition('texter');
            $container->removeAlias(TexterInterface::class);
        }

        foreach (['texter', 'chatter', 'notifier.channel.chat', 'notifier.channel.email', 'notifier.channel.sms', 'notifier.channel.push', 'notifier.channel.desktop'] as $serviceId) {
            if (!$container->hasDefinition($serviceId)) {
                continue;
            }

            if (false === $messageBus = $config['message_bus']) {
                $container->getDefinition($serviceId)->replaceArgument(1, null);
            } else {
                $container->getDefinition($serviceId)->replaceArgument(1, $messageBus ? new Reference($messageBus) : new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            }
        }

        // read by DefaultMessageBusPass, which wires the channels when a default message bus is registered
        $container->setParameter('.notifier.notification_on_failed_messages', $config['notification_on_failed_messages']);

        $container->getDefinition('notifier.channel_policy')->setArgument(0, $config['channel_policy']);

        $container->registerForAutoconfiguration(NotifierTransportFactoryInterface::class)
            ->addTag('chatter.transport_factory');

        $container->registerForAutoconfiguration(NotifierTransportFactoryInterface::class)
            ->addTag('texter.transport_factory');

        $classToServices = [
            NotifierBridge\AllMySms\AllMySmsTransportFactory::class => ['symfony/all-my-sms-notifier', 'notifier.transport_factory.all-my-sms'],
            NotifierBridge\AmazonSns\AmazonSnsTransportFactory::class => ['symfony/amazon-sns-notifier', 'notifier.transport_factory.amazon-sns'],
            NotifierBridge\Bandwidth\BandwidthTransportFactory::class => ['symfony/bandwidth-notifier', 'notifier.transport_factory.bandwidth'],
            NotifierBridge\Bluesky\BlueskyTransportFactory::class => ['symfony/bluesky-notifier', 'notifier.transport_factory.bluesky'],
            NotifierBridge\Brevo\BrevoTransportFactory::class => ['symfony/brevo-notifier', 'notifier.transport_factory.brevo'],
            NotifierBridge\Chatwork\ChatworkTransportFactory::class => ['symfony/chatwork-notifier', 'notifier.transport_factory.chatwork'],
            NotifierBridge\Clickatell\ClickatellTransportFactory::class => ['symfony/clickatell-notifier', 'notifier.transport_factory.clickatell'],
            NotifierBridge\ClickSend\ClickSendTransportFactory::class => ['symfony/click-send-notifier', 'notifier.transport_factory.click-send'],
            NotifierBridge\ContactEveryone\ContactEveryoneTransportFactory::class => ['symfony/contact-everyone-notifier', 'notifier.transport_factory.contact-everyone'],
            NotifierBridge\Discord\DiscordTransportFactory::class => ['symfony/discord-notifier', 'notifier.transport_factory.discord'],
            NotifierBridge\Engagespot\EngagespotTransportFactory::class => ['symfony/engagespot-notifier', 'notifier.transport_factory.engagespot'],
            NotifierBridge\Esendex\EsendexTransportFactory::class => ['symfony/esendex-notifier', 'notifier.transport_factory.esendex'],
            NotifierBridge\Expo\ExpoTransportFactory::class => ['symfony/expo-notifier', 'notifier.transport_factory.expo'],
            NotifierBridge\FacebookPage\FacebookPageTransportFactory::class => ['symfony/facebook-page-notifier', 'notifier.transport_factory.facebook-page'],
            NotifierBridge\Firebase\FirebaseTransportFactory::class => ['symfony/firebase-notifier', 'notifier.transport_factory.firebase'],
            NotifierBridge\FortySixElks\FortySixElksTransportFactory::class => ['symfony/forty-six-elks-notifier', 'notifier.transport_factory.forty-six-elks'],
            NotifierBridge\FreeMobile\FreeMobileTransportFactory::class => ['symfony/free-mobile-notifier', 'notifier.transport_factory.free-mobile'],
            NotifierBridge\GatewayApi\GatewayApiTransportFactory::class => ['symfony/gateway-api-notifier', 'notifier.transport_factory.gateway-api'],
            NotifierBridge\GoIp\GoIpTransportFactory::class => ['symfony/go-ip-notifier', 'notifier.transport_factory.go-ip'],
            NotifierBridge\GoogleChat\GoogleChatTransportFactory::class => ['symfony/google-chat-notifier', 'notifier.transport_factory.google-chat'],
            NotifierBridge\Infobip\InfobipTransportFactory::class => ['symfony/infobip-notifier', 'notifier.transport_factory.infobip'],
            NotifierBridge\Instagram\InstagramTransportFactory::class => ['symfony/instagram-notifier', 'notifier.transport_factory.instagram'],
            NotifierBridge\Iqsms\IqsmsTransportFactory::class => ['symfony/iqsms-notifier', 'notifier.transport_factory.iqsms'],
            NotifierBridge\Isendpro\IsendproTransportFactory::class => ['symfony/isendpro-notifier', 'notifier.transport_factory.isendpro'],
            NotifierBridge\JoliNotif\JoliNotifTransportFactory::class => ['symfony/joli-notif-notifier', 'notifier.transport_factory.joli-notif'],
            NotifierBridge\KazInfoTeh\KazInfoTehTransportFactory::class => ['symfony/kaz-info-teh-notifier', 'notifier.transport_factory.kaz-info-teh'],
            NotifierBridge\LightSms\LightSmsTransportFactory::class => ['symfony/light-sms-notifier', 'notifier.transport_factory.light-sms'],
            NotifierBridge\LineBot\LineBotTransportFactory::class => ['symfony/line-bot-notifier', 'notifier.transport_factory.line-bot'],
            NotifierBridge\LineNotify\LineNotifyTransportFactory::class => ['symfony/line-notify-notifier', 'notifier.transport_factory.line-notify'],
            NotifierBridge\LinkedIn\LinkedInTransportFactory::class => ['symfony/linked-in-notifier', 'notifier.transport_factory.linked-in'],
            NotifierBridge\Lox24\Lox24TransportFactory::class => ['symfony/lox24-notifier', 'notifier.transport_factory.lox24'],
            NotifierBridge\Mailjet\MailjetTransportFactory::class => ['symfony/mailjet-notifier', 'notifier.transport_factory.mailjet'],
            NotifierBridge\Mastodon\MastodonTransportFactory::class => ['symfony/mastodon-notifier', 'notifier.transport_factory.mastodon'],
            NotifierBridge\Matrix\MatrixTransportFactory::class => ['symfony/matrix-notifier', 'notifier.transport_factory.matrix'],
            NotifierBridge\Mattermost\MattermostTransportFactory::class => ['symfony/mattermost-notifier', 'notifier.transport_factory.mattermost'],
            NotifierBridge\Mercure\MercureTransportFactory::class => ['symfony/mercure-notifier', 'notifier.transport_factory.mercure'],
            NotifierBridge\MessageBird\MessageBirdTransportFactory::class => ['symfony/message-bird-notifier', 'notifier.transport_factory.message-bird'],
            NotifierBridge\MessageMedia\MessageMediaTransportFactory::class => ['symfony/message-media-notifier', 'notifier.transport_factory.message-media'],
            NotifierBridge\MicrosoftTeams\MicrosoftTeamsTransportFactory::class => ['symfony/microsoft-teams-notifier', 'notifier.transport_factory.microsoft-teams'],
            NotifierBridge\Mobyt\MobytTransportFactory::class => ['symfony/mobyt-notifier', 'notifier.transport_factory.mobyt'],
            NotifierBridge\Novu\NovuTransportFactory::class => ['symfony/novu-notifier', 'notifier.transport_factory.novu'],
            NotifierBridge\Ntfy\NtfyTransportFactory::class => ['symfony/ntfy-notifier', 'notifier.transport_factory.ntfy'],
            NotifierBridge\Octopush\OctopushTransportFactory::class => ['symfony/octopush-notifier', 'notifier.transport_factory.octopush'],
            NotifierBridge\OneSignal\OneSignalTransportFactory::class => ['symfony/one-signal-notifier', 'notifier.transport_factory.one-signal'],
            NotifierBridge\OrangeSms\OrangeSmsTransportFactory::class => ['symfony/orange-sms-notifier', 'notifier.transport_factory.orange-sms'],
            NotifierBridge\OvhCloud\OvhCloudTransportFactory::class => ['symfony/ovh-cloud-notifier', 'notifier.transport_factory.ovh-cloud'],
            NotifierBridge\PagerDuty\PagerDutyTransportFactory::class => ['symfony/pager-duty-notifier', 'notifier.transport_factory.pager-duty'],
            NotifierBridge\Plivo\PlivoTransportFactory::class => ['symfony/plivo-notifier', 'notifier.transport_factory.plivo'],
            NotifierBridge\Prelude\PreludeTransportFactory::class => ['symfony/prelude-notifier', 'notifier.transport_factory.prelude'],
            NotifierBridge\Primotexto\PrimotextoTransportFactory::class => ['symfony/primotexto-notifier', 'notifier.transport_factory.primotexto'],
            NotifierBridge\Pushover\PushoverTransportFactory::class => ['symfony/pushover-notifier', 'notifier.transport_factory.pushover'],
            NotifierBridge\Pushy\PushyTransportFactory::class => ['symfony/pushy-notifier', 'notifier.transport_factory.pushy'],
            NotifierBridge\Redlink\RedlinkTransportFactory::class => ['symfony/redlink-notifier', 'notifier.transport_factory.redlink'],
            NotifierBridge\RingCentral\RingCentralTransportFactory::class => ['symfony/ring-central-notifier', 'notifier.transport_factory.ring-central'],
            NotifierBridge\RocketChat\RocketChatTransportFactory::class => ['symfony/rocket-chat-notifier', 'notifier.transport_factory.rocket-chat'],
            NotifierBridge\Sendberry\SendberryTransportFactory::class => ['symfony/sendberry-notifier', 'notifier.transport_factory.sendberry'],
            NotifierBridge\Sipgate\SipgateTransportFactory::class => ['symfony/sipgate-notifier', 'notifier.transport_factory.sipgate'],
            NotifierBridge\SimpleTextin\SimpleTextinTransportFactory::class => ['symfony/simple-textin-notifier', 'notifier.transport_factory.simple-textin'],
            NotifierBridge\Sevenio\SevenIoTransportFactory::class => ['symfony/sevenio-notifier', 'notifier.transport_factory.sevenio'],
            NotifierBridge\Sinch\SinchTransportFactory::class => ['symfony/sinch-notifier', 'notifier.transport_factory.sinch'],
            NotifierBridge\Slack\SlackTransportFactory::class => ['symfony/slack-notifier', 'notifier.transport_factory.slack'],
            NotifierBridge\Smsapi\SmsapiTransportFactory::class => ['symfony/smsapi-notifier', 'notifier.transport_factory.smsapi'],
            NotifierBridge\SmsBiuras\SmsBiurasTransportFactory::class => ['symfony/sms-biuras-notifier', 'notifier.transport_factory.sms-biuras'],
            NotifierBridge\Smsbox\SmsboxTransportFactory::class => ['symfony/smsbox-notifier', 'notifier.transport_factory.smsbox'],
            NotifierBridge\Smsc\SmscTransportFactory::class => ['symfony/smsc-notifier', 'notifier.transport_factory.smsc'],
            NotifierBridge\SmsFactor\SmsFactorTransportFactory::class => ['symfony/sms-factor-notifier', 'notifier.transport_factory.sms-factor'],
            NotifierBridge\SmsProxima\SmsProximaTransportFactory::class => ['symfony/sms-proxima-notifier', 'notifier.transport_factory.sms-proxima'],
            NotifierBridge\Smsmode\SmsmodeTransportFactory::class => ['symfony/smsmode-notifier', 'notifier.transport_factory.smsmode'],
            NotifierBridge\SmsSluzba\SmsSluzbaTransportFactory::class => ['symfony/sms-sluzba-notifier', 'notifier.transport_factory.sms-sluzba'],
            NotifierBridge\Smsense\SmsenseTransportFactory::class => ['symfony/smsense-notifier', 'notifier.transport_factory.smsense'],
            NotifierBridge\SpotHit\SpotHitTransportFactory::class => ['symfony/spot-hit-notifier', 'notifier.transport_factory.spot-hit'],
            NotifierBridge\Sweego\SweegoTransportFactory::class => ['symfony/sweego-notifier', 'notifier.transport_factory.sweego'],
            NotifierBridge\Telegram\TelegramTransportFactory::class => ['symfony/telegram-notifier', 'notifier.transport_factory.telegram'],
            NotifierBridge\Telnyx\TelnyxTransportFactory::class => ['symfony/telnyx-notifier', 'notifier.transport_factory.telnyx'],
            NotifierBridge\Termii\TermiiTransportFactory::class => ['symfony/termii-notifier', 'notifier.transport_factory.termii'],
            NotifierBridge\Threads\ThreadsTransportFactory::class => ['symfony/threads-notifier', 'notifier.transport_factory.threads'],
            NotifierBridge\TurboSms\TurboSmsTransportFactory::class => ['symfony/turbo-sms-notifier', 'notifier.transport_factory.turbo-sms'],
            NotifierBridge\Twilio\TwilioTransportFactory::class => ['symfony/twilio-notifier', 'notifier.transport_factory.twilio'],
            NotifierBridge\Twitter\TwitterTransportFactory::class => ['symfony/twitter-notifier', 'notifier.transport_factory.twitter'],
            NotifierBridge\Unifonic\UnifonicTransportFactory::class => ['symfony/unifonic-notifier', 'notifier.transport_factory.unifonic'],
            NotifierBridge\Vonage\VonageTransportFactory::class => ['symfony/vonage-notifier', 'notifier.transport_factory.vonage'],
            NotifierBridge\WhatsApp\WhatsAppTransportFactory::class => ['symfony/whats-app-notifier', 'notifier.transport_factory.whats-app'],
            NotifierBridge\Yunpian\YunpianTransportFactory::class => ['symfony/yunpian-notifier', 'notifier.transport_factory.yunpian'],
            NotifierBridge\Zendesk\ZendeskTransportFactory::class => ['symfony/zendesk-notifier', 'notifier.transport_factory.zendesk'],
            NotifierBridge\Zulip\ZulipTransportFactory::class => ['symfony/zulip-notifier', 'notifier.transport_factory.zulip'],
        ];

        foreach ($classToServices as $class => [$package, $service]) {
            if (!ContainerBuilder::willBeAvailable($package, $class, ['symfony/framework-bundle', 'symfony/notifier'])) {
                $container->removeDefinition($service);
            }
        }

        if (ContainerBuilder::willBeAvailable('symfony/mercure-notifier', NotifierBridge\Mercure\MercureTransportFactory::class, ['symfony/framework-bundle', 'symfony/notifier']) && ContainerBuilder::willBeAvailable('symfony/mercure-bundle', MercureBundle::class, ['symfony/framework-bundle', 'symfony/notifier']) && \in_array(MercureBundle::class, $container->getParameter('kernel.bundles'), true)) {
            $container->getDefinition('notifier.transport_factory.mercure')
                ->replaceArgument(0, new Reference(HubRegistry::class))
                ->replaceArgument(1, new Reference('event_dispatcher', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->addArgument(new Reference('http_client', ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        } elseif (ContainerBuilder::willBeAvailable('symfony/mercure-notifier', NotifierBridge\Mercure\MercureTransportFactory::class, ['symfony/framework-bundle', 'symfony/notifier'])) {
            $container->removeDefinition('notifier.transport_factory.mercure');
        }

        // don't use ContainerBuilder::willBeAvailable() as these are not needed in production
        if (class_exists(FakeChatTransportFactory::class)) {
            $container->getDefinition('notifier.transport_factory.fake-chat')
                ->replaceArgument(0, new Reference('mailer', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->replaceArgument(1, new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->addArgument(new Reference('event_dispatcher', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->addArgument(new Reference('http_client', ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        } else {
            $container->removeDefinition('notifier.transport_factory.fake-chat');
        }

        // don't use ContainerBuilder::willBeAvailable() as these are not needed in production
        if (class_exists(FakeSmsTransportFactory::class)) {
            $container->getDefinition('notifier.transport_factory.fake-sms')
                ->replaceArgument(0, new Reference('mailer', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->replaceArgument(1, new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->addArgument(new Reference('event_dispatcher', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->addArgument(new Reference('http_client', ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        } else {
            $container->removeDefinition('notifier.transport_factory.fake-sms');
        }

        if (ContainerBuilder::willBeAvailable('symfony/bluesky-notifier', NotifierBridge\Bluesky\BlueskyTransportFactory::class, ['symfony/framework-bundle', 'symfony/notifier'])) {
            $container->getDefinition('notifier.transport_factory.bluesky')
                ->addArgument(new Reference('logger'))
                ->addArgument(new Reference('clock', ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        }

        if (isset($config['admin_recipients'])) {
            $notifier = $container->getDefinition('notifier');
            foreach ($config['admin_recipients'] as $i => $recipient) {
                $id = 'notifier.admin_recipient.'.$i;
                $container->setDefinition($id, new Definition(Recipient::class, [$recipient['email'], $recipient['phone']]));
                $notifier->addMethodCall('addAdminRecipient', [new Reference($id)]);
            }
        }

        if (class_exists(WebhookController::class)) {
            $configurator->import('Resources/config/notifier_webhook.php');

            $webhookRequestParsers = [
                NotifierBridge\Lox24\Webhook\Lox24RequestParser::class => ['symfony/lox24-notifier', 'notifier.webhook.request_parser.lox24'],
                NotifierBridge\Smsbox\Webhook\SmsboxRequestParser::class => ['symfony/smsbox-notifier', 'notifier.webhook.request_parser.smsbox'],
                NotifierBridge\Sweego\Webhook\SweegoRequestParser::class => ['symfony/sweego-notifier', 'notifier.webhook.request_parser.sweego'],
                NotifierBridge\Twilio\Webhook\TwilioRequestParser::class => ['symfony/twilio-notifier', 'notifier.webhook.request_parser.twilio'],
                NotifierBridge\Vonage\Webhook\VonageRequestParser::class => ['symfony/vonage-notifier', 'notifier.webhook.request_parser.vonage'],
            ];

            foreach ($webhookRequestParsers as $class => [$package, $service]) {
                if (!ContainerBuilder::willBeAvailable($package, $class, ['symfony/framework-bundle', 'symfony/notifier'])) {
                    $container->removeDefinition($service);
                }
            }
        }
    }
}
