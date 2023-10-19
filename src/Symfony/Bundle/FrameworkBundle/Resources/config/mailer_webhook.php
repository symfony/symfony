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

use Symfony\Component\Mailer\Bridge\Mailgun\RemoteEvent\MailgunPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailgun\Webhook\MailgunRequestParser;
use Symfony\Component\Mailer\Bridge\Postmark\RemoteEvent\PostmarkPayloadConverter;
use Symfony\Component\Mailer\Bridge\Postmark\Webhook\PostmarkRequestParser;
use Symfony\Component\Mailer\Bridge\Sendgrid\RemoteEvent\SendgridPayloadConverter;
use Symfony\Component\Mailer\Bridge\Sendgrid\Webhook\SendgridRequestParser;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('mailer.payload_converter.mailgun', MailgunPayloadConverter::class)
        ->set('mailer.webhook.request_parser.mailgun', MailgunRequestParser::class)
            ->args([service('mailer.payload_converter.mailgun')])
        ->alias(MailgunRequestParser::class, 'mailer.webhook.request_parser.mailgun')

        ->set('mailer.payload_converter.postmark', PostmarkPayloadConverter::class)
        ->set('mailer.webhook.request_parser.postmark', PostmarkRequestParser::class)
            ->args([service('mailer.payload_converter.postmark')])
        ->alias(PostmarkRequestParser::class, 'mailer.webhook.request_parser.postmark')

        ->set('mailer.payload_converter.sendgrid', SendgridPayloadConverter::class)
        ->set('mailer.webhook.request_parser.sendgrid', SendgridRequestParser::class)
            ->args([service('mailer.payload_converter.sendgrid')])
        ->alias(SendgridRequestParser::class, 'mailer.webhook.request_parser.sendgrid')
    ;
};
