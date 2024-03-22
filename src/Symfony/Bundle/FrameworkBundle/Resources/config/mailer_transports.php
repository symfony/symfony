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
use Symfony\Component\Mailer\Bridge\Azure\Transport\AzureTransportFactory;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Bridge\Infobip\Transport\InfobipTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillTransportFactory;
use Symfony\Component\Mailer\Bridge\MailerSend\Transport\MailerSendTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetTransportFactory;
use Symfony\Component\Mailer\Bridge\MailPace\Transport\MailPaceTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Bridge\Resend\Transport\ResendTransportFactory;
use Symfony\Component\Mailer\Bridge\Scaleway\Transport\ScalewayTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
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
            ->tag('monolog.logger', ['channel' => 'mailer']);

    $factories = [
        'amazon' => SesTransportFactory::class,
        'azure' => AzureTransportFactory::class,
        'brevo' => BrevoTransportFactory::class,
        'gmail' => GmailTransportFactory::class,
        'infobip' => InfobipTransportFactory::class,
        'mailchimp' => MandrillTransportFactory::class,
        'mailersend' => MailerSendTransportFactory::class,
        'mailgun' => MailgunTransportFactory::class,
        'mailjet' => MailjetTransportFactory::class,
        'mailpace' => MailPaceTransportFactory::class,
        'native' => NativeTransportFactory::class,
        'null' => NullTransportFactory::class,
        'postmark' => PostmarkTransportFactory::class,
        'resend' => ResendTransportFactory::class,
        'scaleway' => ScalewayTransportFactory::class,
        'sendgrid' => SendgridTransportFactory::class,
        'sendmail' => SendmailTransportFactory::class,
        'smtp' => EsmtpTransportFactory::class,
    ];

    foreach ($factories as $name => $class) {
        $container->services()
            ->set('mailer.transport_factory.'.$name, $class)
                ->parent('mailer.transport_factory.abstract')
                ->tag('mailer.transport_factory');
    }
};
