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
 *
 * @experimental in 5.2
 */
class UnsupportedSchemeException extends LogicException
{
    private const SCHEME_TO_PACKAGE_MAP = [
        'slack' => [
            'class' => Bridge\Slack\SlackTransportFactory::class,
            'package' => 'symfony/slack-notifier',
        ],
        'telegram' => [
            'class' => Bridge\Telegram\TelegramTransportFactory::class,
            'package' => 'symfony/telegram-notifier',
        ],
        'mattermost' => [
            'class' => Bridge\Mattermost\MattermostTransportFactory::class,
            'package' => 'symfony/mattermost-notifier',
        ],
        'googlechat' => [
            'class' => Bridge\GoogleChat\GoogleChatTransportFactory::class,
            'package' => 'symfony/google-chat-notifier',
        ],
        'nexmo' => [
            'class' => Bridge\Nexmo\NexmoTransportFactory::class,
            'package' => 'symfony/nexmo-notifier',
        ],
        'rocketchat' => [
            'class' => Bridge\RocketChat\RocketChatTransportFactory::class,
            'package' => 'symfony/rocket-chat-notifier',
        ],
        'twilio' => [
            'class' => Bridge\Twilio\TwilioTransportFactory::class,
            'package' => 'symfony/twilio-notifier',
        ],
        'infobip' => [
            'class' => Bridge\Infobip\InfobipTransportFactory::class,
            'package' => 'symfony/infobip-notifier',
        ],
        'firebase' => [
            'class' => Bridge\Firebase\FirebaseTransportFactory::class,
            'package' => 'symfony/firebase-notifier',
        ],
        'freemobile' => [
            'class' => Bridge\FreeMobile\FreeMobileTransportFactory::class,
            'package' => 'symfony/free-mobile-notifier',
        ],
        'ovhcloud' => [
            'class' => Bridge\OvhCloud\OvhCloudTransportFactory::class,
            'package' => 'symfony/ovh-cloud-notifier',
        ],
        'sinch' => [
            'class' => Bridge\Sinch\SinchTransportFactory::class,
            'package' => 'symfony/sinch-notifier',
        ],
        'zulip' => [
            'class' => Bridge\Zulip\ZulipTransportFactory::class,
            'package' => 'symfony/zulip-notifier',
        ],
        'smsapi' => [
            'class' => Bridge\Smsapi\SmsapiTransportFactory::class,
            'package' => 'symfony/smsapi-notifier',
        ],
        'esendex' => [
            'class' => Bridge\Esendex\EsendexTransportFactory::class,
            'package' => 'symfony/esendex-notifier',
        ],
        'discord' => [
            'class' => Bridge\Discord\DiscordTransportFactory::class,
            'package' => 'symfony/discord-notifier',
        ],
    ];

    /**
     * @param string[] $supported
     */
    public function __construct(Dsn $dsn, string $name = null, array $supported = [], \Throwable $previous = null)
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

        parent::__construct($message.'.', 0, $previous);
    }
}
