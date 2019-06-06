<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun;

use Symfony\Component\Mailer\Exception\RuntimeException;

/**
 * @author Michael Garifullin <garifullin@gmail.com>
 *
 * @experimental in 4.3
 */
class MailgunRegionConfiguration
{
    public const REGION_DEFAULT = self::REGION_US;
    public const REGION_EU = 'EU';
    public const REGION_US = 'US';

    private const SMTP_HOSTS = [
        self::REGION_EU => 'smtp.eu.mailgun.org',
        self::REGION_US => 'smtp.mailgun.org',
    ];

    private const ENDPOINT_DOMAINS = [
        self::REGION_EU => 'api.eu.mailgun.net',
        self::REGION_US => 'api.mailgun.net',
    ];

    private const HTTP_API_ENDPOINT = 'https://%s/v3/%s/messages';
    private const HTTP_ENDPOINT = 'https://%s/v3/%s/messages.mime';

    public static function resolveSmtpDomainByRegion(string $region = self::REGION_US): string
    {
        return self::resolveRegionArray(self::SMTP_HOSTS, $region);
    }

    public static function resolveApiEndpoint(string $domain, string $region = self::REGION_US): string
    {
        return sprintf(self::HTTP_API_ENDPOINT, self::resolveHttpDomainByRegion($region), urlencode($domain));
    }

    public static function resolveHttpEndpoint(string $domain, string $region = self::REGION_US): string
    {
        return sprintf(self::HTTP_ENDPOINT, self::resolveHttpDomainByRegion($region), urlencode($domain));
    }

    private static function resolveHttpDomainByRegion(string $region = self::REGION_DEFAULT): string
    {
        return self::resolveRegionArray(self::ENDPOINT_DOMAINS, $region);
    }

    private static function resolveRegionArray(array $regionMapping, string $region = self::REGION_DEFAULT): string
    {
        if (empty($regionMapping[$region])) {
            throw new RuntimeException(sprintf('Region "%s" for Mailgun is incorrect', $region));
        }

        return $regionMapping[$region];
    }
}
