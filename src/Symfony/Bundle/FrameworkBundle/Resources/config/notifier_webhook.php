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

use Symfony\Component\Notifier\Bridge\Smsbox\Webhook\SmsboxRequestParser;
use Symfony\Component\Notifier\Bridge\Sweego\Webhook\SweegoRequestParser;
use Symfony\Component\Notifier\Bridge\Twilio\Webhook\TwilioRequestParser;
use Symfony\Component\Notifier\Bridge\Vonage\Webhook\VonageRequestParser;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('notifier.webhook.request_parser.smsbox', SmsboxRequestParser::class)
        ->alias(SmsboxRequestParser::class, 'notifier.webhook.request_parser.smsbox')

        ->set('notifier.webhook.request_parser.sweego', SweegoRequestParser::class)
        ->alias(SweegoRequestParser::class, 'notifier.webhook.request_parser.sweego')

        ->set('notifier.webhook.request_parser.twilio', TwilioRequestParser::class)
        ->alias(TwilioRequestParser::class, 'notifier.webhook.request_parser.twilio')

        ->set('notifier.webhook.request_parser.vonage', VonageRequestParser::class)
        ->alias(VonageRequestParser::class, 'notifier.webhook.request_parser.vonage')
    ;
};
