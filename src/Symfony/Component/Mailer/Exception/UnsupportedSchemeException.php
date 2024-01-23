<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Exception;

use Symfony\Component\Mailer\Bridge;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
class UnsupportedSchemeException extends LogicException
{
    private const SCHEME_TO_PACKAGE_MAP = [
        'gmail' => [
            'class' => Bridge\Google\Transport\GmailTransportFactory::class,
            'package' => 'symfony/google-mailer',
        ],
        'mailgun' => [
            'class' => Bridge\Mailgun\Transport\MailgunTransportFactory::class,
            'package' => 'symfony/mailgun-mailer',
        ],
        'mailjet' => [
            'class' => Bridge\Mailjet\Transport\MailjetTransportFactory::class,
            'package' => 'symfony/mailjet-mailer',
        ],
        'mandrill' => [
            'class' => Bridge\Mailchimp\Transport\MandrillTransportFactory::class,
            'package' => 'symfony/mailchimp-mailer',
        ],
        'ohmysmtp' => [
            'class' => Bridge\OhMySmtp\Transport\OhMySmtpTransportFactory::class,
            'package' => 'symfony/oh-my-smtp-mailer',
        ],
        'postmark' => [
            'class' => Bridge\Postmark\Transport\PostmarkTransportFactory::class,
            'package' => 'symfony/postmark-mailer',
        ],
        'sendgrid' => [
            'class' => Bridge\Sendgrid\Transport\SendgridTransportFactory::class,
            'package' => 'symfony/sendgrid-mailer',
        ],
        'sendinblue' => [
            'class' => Bridge\Sendinblue\Transport\SendinblueTransportFactory::class,
            'package' => 'symfony/sendinblue-mailer',
        ],
        'ses' => [
            'class' => Bridge\Amazon\Transport\SesTransportFactory::class,
            'package' => 'symfony/amazon-mailer',
        ],
    ];

    public function __construct(Dsn $dsn, ?string $name = null, array $supported = [])
    {
        $provider = $dsn->getScheme();
        if (false !== $pos = strpos($provider, '+')) {
            $provider = substr($provider, 0, $pos);
        }
        $package = self::SCHEME_TO_PACKAGE_MAP[$provider] ?? null;
        if ($package && !class_exists($package['class'])) {
            parent::__construct(sprintf('Unable to send emails via "%s" as the bridge is not installed; try running "composer require %s".', $provider, $package['package']));

            return;
        }

        $message = sprintf('The "%s" scheme is not supported', $dsn->getScheme());
        if ($name && $supported) {
            $message .= sprintf('; supported schemes for mailer "%s" are: "%s"', $name, implode('", "', $supported));
        }

        parent::__construct($message.'.');
    }
}
