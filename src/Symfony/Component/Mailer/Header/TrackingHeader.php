<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Header;

use Symfony\Component\Mime\Header\UnstructuredHeader;

/**
 * Controls per-message open and click tracking; a null flag keeps the provider/transport default.
 *
 * Supported by the AhaSend, Infobip, Mailchimp (Mandrill), Mailgun, Mailjet, Postmark and Sendgrid
 * bridges on both their API and SMTP transports, and by the Azure, Brevo and MailerSend bridges on
 * their API transport. Azure and Brevo only expose a single combined toggle, so tracking is
 * disabled as soon as either flag is explicitly false, and enabled as soon as either is explicitly
 * true; note that Brevo's toggle anonymises the open/click events rather than disabling them
 * outright. Mandrill's SMTP header can only list the aspects to enable, so when one flag is set
 * there, a null aspect is disabled rather than left to the account default. An explicit
 * provider-specific header or setting always wins over this one. On transports that don't support
 * this header (plain SMTP, SES, Resend, ...), it is sent as a literal `X-Track` header and has no
 * effect.
 */
final class TrackingHeader extends UnstructuredHeader
{
    public function __construct(
        private readonly ?bool $opens = null,
        private readonly ?bool $clicks = null,
    ) {
        parent::__construct('X-Track', self::formatValue($opens, $clicks));
    }

    public function getOpens(): ?bool
    {
        return $this->opens;
    }

    public function getClicks(): ?bool
    {
        return $this->clicks;
    }

    private static function formatValue(?bool $opens, ?bool $clicks): string
    {
        return \sprintf('opens=%s; clicks=%s', self::formatFlag($opens), self::formatFlag($clicks));
    }

    private static function formatFlag(?bool $flag): string
    {
        return match ($flag) {
            null => 'default',
            true => 'true',
            false => 'false',
        };
    }
}
