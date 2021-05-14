<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Exception;

use Symfony\Component\Notifier\Bridge;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
class UnsupportedSchemeException extends LogicException
{
    private const SCHEME_TO_PACKAGE_MAP = [
        'allmysms' => [
            'class' => Bridge\AllMySms\AllMySmsTransportFactory::class,
            'package' => 'symfony/allmysms-notifier',
        ],
        'clickatell' => [
            'class' => Bridge\Clickatell\ClickatellTransportFactory::class,
            'package' => 'symfony/clickatell-notifier',
        ],
        'discord' => [
            'class' => Bridge\Discord\DiscordTransportFactory::class,
            'package' => 'symfony/discord-notifier',
        ],
        'esendex' => [
            'class' => Bridge\Esendex\EsendexTransportFactory::class,
            'package' => 'symfony/esendex-notifier',
        ],
        'fakechat' => [
            'class' => Bridge\FakeChat\FakeChatTransportFactory::class,
            'package' => 'symfony/fake-chat-notifier',
        ],
        'fakesms' => [
            'class' => Bridge\FakeSms\FakeSmsTransportFactory::class,
            'package' => 'symfony/fake-sms-notifier',
        ],
        'firebase' => [
            'class' => Bridge\Firebase\FirebaseTransportFactory::class,
            'package' => 'symfony/firebase-notifier',
        ],
        'freemobile' => [
            'class' => Bridge\FreeMobile\FreeMobileTransportFactory::class,
            'package' => 'symfony/free-mobile-notifier',
        ],
        'gatewayapi' => [
            'class' => Bridge\GatewayApi\GatewayApiTransportFactory::class,
            'package' => 'symfony/gatewayapi-notifier',
        ],
        'gitter' => [
            'class' => Bridge\Gitter\GitterTransportFactory::class,
            'package' => 'symfony/gitter-notifier',
        ],
        'googlechat' => [
            'class' => Bridge\GoogleChat\GoogleChatTransportFactory::class,
            'package' => 'symfony/google-chat-notifier',
        ],
        'infobip' => [
            'class' => Bridge\Infobip\InfobipTransportFactory::class,
            'package' => 'symfony/infobip-notifier',
        ],
        'iqsms' => [
            'class' => Bridge\Iqsms\IqsmsTransportFactory::class,
            'package' => 'symfony/iqsms-notifier',
        ],
        'lightsms' => [
            'class' => Bridge\LightSms\LightSmsTransportFactory::class,
            'package' => 'symfony/light-sms-notifier',
        ],
        'linkedin' => [
            'class' => Bridge\LinkedIn\LinkedInTransportFactory::class,
            'package' => 'symfony/linked-in-notifier',
        ],
        'mattermost' => [
            'class' => Bridge\Mattermost\MattermostTransportFactory::class,
            'package' => 'symfony/mattermost-notifier',
        ],
        'mercure' => [
            'class' => Bridge\Mercure\MercureTransportFactory::class,
            'package' => 'symfony/mercure-notifier',
        ],
        'messagebird' => [
            'class' => Bridge\MessageBird\MessageBirdTransportFactory::class,
            'package' => 'symfony/message-bird-notifier',
        ],
        'microsoftteams' => [
            'class' => Bridge\MicrosoftTeams\MicrosoftTeamsTransportFactory::class,
            'package' => 'symfony/microsoft-teams-notifier',
        ],
        'mobyt' => [
            'class' => Bridge\Mobyt\MobytTransportFactory::class,
            'package' => 'symfony/mobyt-notifier',
        ],
        'nexmo' => [
            'class' => Bridge\Nexmo\NexmoTransportFactory::class,
            'package' => 'symfony/nexmo-notifier',
        ],
        'octopush' => [
            'class' => Bridge\Octopush\OctopushTransportFactory::class,
            'package' => 'symfony/octopush-notifier',
        ],
        'ovhcloud' => [
            'class' => Bridge\OvhCloud\OvhCloudTransportFactory::class,
            'package' => 'symfony/ovh-cloud-notifier',
        ],
        'rocketchat' => [
            'class' => Bridge\RocketChat\RocketChatTransportFactory::class,
            'package' => 'symfony/rocket-chat-notifier',
        ],
        'sendinblue' => [
            'class' => Bridge\Sendinblue\SendinblueTransportFactory::class,
            'package' => 'symfony/sendinblue-notifier',
        ],
        'sinch' => [
            'class' => Bridge\Sinch\SinchTransportFactory::class,
            'package' => 'symfony/sinch-notifier',
        ],
        'slack' => [
            'class' => Bridge\Slack\SlackTransportFactory::class,
            'package' => 'symfony/slack-notifier',
        ],
        'smsapi' => [
            'class' => Bridge\Smsapi\SmsapiTransportFactory::class,
            'package' => 'symfony/smsapi-notifier',
        ],
        'smsbiuras' => [
            'class' => Bridge\SmsBiuras\SmsBiurasTransportFactory::class,
            'package' => 'symfony/sms-biuras-notifier',
        ],
        'spothit' => [
            'class' => Bridge\SpotHit\SpotHitTransportFactory::class,
            'package' => 'symfony/spot-hit-notifier',
        ],
        'telegram' => [
            'class' => Bridge\Telegram\TelegramTransportFactory::class,
            'package' => 'symfony/telegram-notifier',
        ],
        'twilio' => [
            'class' => Bridge\Twilio\TwilioTransportFactory::class,
            'package' => 'symfony/twilio-notifier',
        ],
        'zulip' => [
            'class' => Bridge\Zulip\ZulipTransportFactory::class,
            'package' => 'symfony/zulip-notifier',
        ],
    ];

    /**
     * @param string[] $supported
     */
    public function __construct(Dsn $dsn, string $name = null, array $supported = [])
    {
        $provider = $dsn->getScheme();
        if (false !== $pos = strpos($provider, '+')) {
            $provider = substr($provider, 0, $pos);
        }
        $package = self::SCHEME_TO_PACKAGE_MAP[$provider] ?? null;
        if ($package && !class_exists($package['class'])) {
            parent::__construct(sprintf('Unable to send notification via "%s" as the bridge is not installed; try running "composer require %s".', $provider, $package['package']));

            return;
        }

        $message = sprintf('The "%s" scheme is not supported', $dsn->getScheme());
        if ($name && $supported) {
            $message .= sprintf('; supported schemes for notifier "%s" are: "%s"', $name, implode('", "', $supported));
        }

        parent::__construct($message.'.');
    }
}
