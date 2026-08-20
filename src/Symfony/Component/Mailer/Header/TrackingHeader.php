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

use Symfony\Component\Mime\Header\Headers;
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
 *
 * The header is resolved by name, so an application-wide default can be configured as a plain
 * header, e.g. `framework.mailer.headers: { 'X-Track': 'opens=false; clicks=false' }`; a
 * TrackingHeader set on the message itself wins over such a default.
 */
final class TrackingHeader extends UnstructuredHeader
{
    public const NAME = 'X-Track';

    public function __construct(
        private readonly ?bool $opens = null,
        private readonly ?bool $clicks = null,
    ) {
        parent::__construct(self::NAME, self::formatValue($opens, $clicks));
    }

    /**
     * Resolves the tracking flags carried by a set of headers.
     *
     * The header is looked up by name and can be a plain text header, as produced by the
     * "headers" option of the mailer configuration or by a MIME round trip.
     */
    public static function fromHeaders(Headers $headers): ?self
    {
        if (null === $header = $headers->get(self::NAME)) {
            return null;
        }

        if ($header instanceof self) {
            return $header;
        }

        $flags = ['opens' => null, 'clicks' => null];
        foreach (explode(';', $header->getBody()) as $part) {
            if (2 === \count($part = explode('=', trim($part), 2)) && \array_key_exists($part[0], $flags)) {
                $flags[$part[0]] = match (trim($part[1])) {
                    'true' => true,
                    'false' => false,
                    default => null,
                };
            }
        }

        return new self($flags['opens'], $flags['clicks']);
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
