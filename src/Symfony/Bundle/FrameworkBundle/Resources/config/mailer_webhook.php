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

use Symfony\Component\Mailer\Bridge\Brevo\RemoteEvent\BrevoPayloadConverter;
use Symfony\Component\Mailer\Bridge\Brevo\Webhook\BrevoRequestParser;
use Symfony\Component\Mailer\Bridge\Mailchimp\RemoteEvent\MailchimpPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailchimp\Webhook\MailchimpRequestParser;
use Symfony\Component\Mailer\Bridge\MailerSend\RemoteEvent\MailerSendPayloadConverter;
use Symfony\Component\Mailer\Bridge\MailerSend\Webhook\MailerSendRequestParser;
use Symfony\Component\Mailer\Bridge\Mailgun\RemoteEvent\MailgunPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailgun\Webhook\MailgunRequestParser;
use Symfony\Component\Mailer\Bridge\Mailjet\RemoteEvent\MailjetPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailjet\Webhook\MailjetRequestParser;
use Symfony\Component\Mailer\Bridge\Mailomat\RemoteEvent\MailomatPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailomat\Webhook\MailomatRequestParser;
use Symfony\Component\Mailer\Bridge\Mailtrap\RemoteEvent\MailtrapPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailtrap\Webhook\MailtrapRequestParser;
use Symfony\Component\Mailer\Bridge\Postmark\RemoteEvent\PostmarkPayloadConverter;
use Symfony\Component\Mailer\Bridge\Postmark\Webhook\PostmarkRequestParser;
use Symfony\Component\Mailer\Bridge\Resend\RemoteEvent\ResendPayloadConverter;
use Symfony\Component\Mailer\Bridge\Resend\Webhook\ResendRequestParser;
use Symfony\Component\Mailer\Bridge\Sendgrid\RemoteEvent\SendgridPayloadConverter;
use Symfony\Component\Mailer\Bridge\Sendgrid\Webhook\SendgridRequestParser;
use Symfony\Component\Mailer\Bridge\Sweego\RemoteEvent\SweegoPayloadConverter;
use Symfony\Component\Mailer\Bridge\Sweego\Webhook\SweegoRequestParser;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('mailer.payload_converter.brevo', BrevoPayloadConverter::class)
        ->set('mailer.webhook.request_parser.brevo', BrevoRequestParser::class)
            ->args([service('mailer.payload_converter.brevo')])
        ->alias(BrevoRequestParser::class, 'mailer.webhook.request_parser.brevo')

        ->set('mailer.payload_converter.mailersend', MailerSendPayloadConverter::class)
        ->set('mailer.webhook.request_parser.mailersend', MailerSendRequestParser::class)
            ->args([service('mailer.payload_converter.mailersend')])
        ->alias(MailerSendRequestParser::class, 'mailer.webhook.request_parser.mailersend')

        ->set('mailer.payload_converter.mailgun', MailgunPayloadConverter::class)
        ->set('mailer.webhook.request_parser.mailgun', MailgunRequestParser::class)
            ->args([service('mailer.payload_converter.mailgun')])
        ->alias(MailgunRequestParser::class, 'mailer.webhook.request_parser.mailgun')

        ->set('mailer.payload_converter.mailjet', MailjetPayloadConverter::class)
        ->set('mailer.webhook.request_parser.mailjet', MailjetRequestParser::class)
            ->args([service('mailer.payload_converter.mailjet')])
        ->alias(MailjetRequestParser::class, 'mailer.webhook.request_parser.mailjet')

        ->set('mailer.payload_converter.mailomat', MailomatPayloadConverter::class)
        ->set('mailer.webhook.request_parser.mailomat', MailomatRequestParser::class)
            ->args([service('mailer.payload_converter.mailomat')])
        ->alias(MailomatRequestParser::class, 'mailer.webhook.request_parser.mailomat')

        ->set('mailer.payload_converter.postmark', PostmarkPayloadConverter::class)
        ->set('mailer.webhook.request_parser.postmark', PostmarkRequestParser::class)
            ->args([service('mailer.payload_converter.postmark')])
        ->alias(PostmarkRequestParser::class, 'mailer.webhook.request_parser.postmark')

        ->set('mailer.payload_converter.mailtrap', MailtrapPayloadConverter::class)
        ->set('mailer.webhook.request_parser.mailtrap', MailtrapRequestParser::class)
            ->args([service('mailer.payload_converter.mailtrap')])
        ->alias(MailtrapRequestParser::class, 'mailer.webhook.request_parser.mailtrap')

        ->set('mailer.payload_converter.resend', ResendPayloadConverter::class)
        ->set('mailer.webhook.request_parser.resend', ResendRequestParser::class)
            ->args([service('mailer.payload_converter.resend')])
        ->alias(ResendRequestParser::class, 'mailer.webhook.request_parser.resend')

        ->set('mailer.payload_converter.sendgrid', SendgridPayloadConverter::class)
        ->set('mailer.webhook.request_parser.sendgrid', SendgridRequestParser::class)
            ->args([service('mailer.payload_converter.sendgrid')])
        ->alias(SendgridRequestParser::class, 'mailer.webhook.request_parser.sendgrid')

        ->set('mailer.payload_converter.sweego', SweegoPayloadConverter::class)
        ->set('mailer.webhook.request_parser.sweego', SweegoRequestParser::class)
            ->args([service('mailer.payload_converter.sweego')])
        ->alias(SweegoRequestParser::class, 'mailer.webhook.request_parser.sweego')

        ->set('mailer.payload_converter.mailchimp', MailchimpPayloadConverter::class)
        ->set('mailer.webhook.request_parser.mailchimp', MailchimpRequestParser::class)
            ->args([service('mailer.payload_converter.mailchimp')])
        ->alias(MailchimpRequestParser::class, 'mailer.webhook.request_parser.mailchimp')
    ;
};
