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
            'class' => Bridge\Google\Factory\GmailTransportFactory::class,
            'package' => 'symfony/google-mailer',
        ],
        'mailgun' => [
            'class' => Bridge\Mailgun\Factory\MailgunTransportFactory::class,
            'package' => 'symfony/mailgun-mailer',
        ],
        'postmark' => [
            'class' => Bridge\Postmark\Factory\PostmarkTransportFactory::class,
            'package' => 'symfony/postmark-mailer',
        ],
        'sendgrid' => [
            'class' => Bridge\Sendgrid\Factory\SendgridTransportFactory::class,
            'package' => 'symfony/sendgrid-mailer',
        ],
        'ses' => [
            'class' => Bridge\Amazon\Factory\SesTransportFactory::class,
            'package' => 'symfony/amazon-mailer',
        ],
        'mandrill' => [
            'class' => Bridge\Mailchimp\Factory\MandrillTransportFactory::class,
            'package' => 'symfony/mailchimp-mailer',
        ],
    ];

    public function __construct(Dsn $dsn)
    {
        $host = $dsn->getHost();
        $package = self::HOST_TO_PACKAGE_MAP[$host] ?? null;
        if ($package && !class_exists($package['class'])) {
            parent::__construct(sprintf('Unable to send emails via "%s" as the bridge is not installed. Try running "composer require %s".', $host, $package['package']));

            return;
        }

        parent::__construct(sprintf('The "%s" mailer is not supported.', $host));
    }
}
