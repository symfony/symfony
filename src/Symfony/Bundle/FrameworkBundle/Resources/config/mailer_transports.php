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

use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendinblue\Transport\SendinblueTransportFactory;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\NativeTransportFactory;
use Symfony\Component\Mailer\Transport\NullTransportFactory;
use Symfony\Component\Mailer\Transport\SendmailTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('mailer.transport_factory.abstract', AbstractTransportFactory::class)
            ->abstract()
            ->args([
                service('event_dispatcher'),
                service('http_client')->ignoreOnInvalid(),
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'mailer'])

        ->set('mailer.transport_factory.amazon', SesTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory')

        ->set('mailer.transport_factory.gmail', GmailTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory')

        ->set('mailer.transport_factory.mailchimp', MandrillTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory')

        ->set('mailer.transport_factory.mailjet', MailjetTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory')

        ->set('mailer.transport_factory.mailgun', MailgunTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory')

        ->set('mailer.transport_factory.postmark', PostmarkTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory')

        ->set('mailer.transport_factory.sendgrid', SendgridTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory')

        ->set('mailer.transport_factory.null', NullTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory')

        ->set('mailer.transport_factory.sendmail', SendmailTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory')

        ->set('mailer.transport_factory.sendinblue', SendinblueTransportFactory::class)
        ->parent('mailer.transport_factory.abstract')
        ->tag('mailer.transport_factory')

        ->set('mailer.transport_factory.smtp', EsmtpTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory', ['priority' => -100])

        ->set('mailer.transport_factory.native', NativeTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory');
};
