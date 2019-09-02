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
class UnsupportedHostException extends LogicException
{
    private const HOST_TO_PACKAGE_MAP = [
        'gmail' => [
            'class' => Bridge\Google\Transport\GmailTransportFactory::class,
            'package' => 'symfony/google-mailer',
        ],
        'mailgun' => [
            'class' => Bridge\Mailgun\Transport\MailgunTransportFactory::class,
            'package' => 'symfony/mailgun-mailer',
        ],
        'postmark' => [
            'class' => Bridge\Postmark\Transport\PostmarkTransportFactory::class,
            'package' => 'symfony/postmark-mailer',
        ],
        'sendgrid' => [
            'class' => Bridge\Sendgrid\Transport\SendgridTransportFactory::class,
            'package' => 'symfony/sendgrid-mailer',
        ],
        'ses' => [
            'class' => Bridge\Amazon\Transport\SesTransportFactory::class,
            'package' => 'symfony/amazon-mailer',
        ],
        'mandrill' => [
            'class' => Bridge\Mailchimp\Transport\MandrillTransportFactory::class,
            'package' => 'symfony/mailchimp-mailer',
        ],
    ];

    public function __construct(Dsn $dsn)
    {
        $provider = $dsn->getScheme();
        if (false !== $pos = strpos($provider, '+')) {
            $provider = substr($provider, 0, $pos);
        }
        $package = self::HOST_TO_PACKAGE_MAP[$provider] ?? null;
        if ($package && !class_exists($package['class'])) {
            parent::__construct(sprintf('Unable to send emails via "%s" as the bridge is not installed. Try running "composer require %s".', $host, $package['package']));

            return;
        }

        parent::__construct(sprintf('The "%s" scheme is not supported.', $dsn->getScheme()));
    }
}
