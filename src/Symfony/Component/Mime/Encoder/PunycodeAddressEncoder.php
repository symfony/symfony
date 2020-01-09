<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Encoder;

use Symfony\Component\Mime\Exception\AddressEncoderException;

/**
 * A punycode IDN email address encoder.
 *
 * This encoder supports email addresses with non-ASCII characters.
 *
 * @author Saif Eddin Gmati <saif.gmati@symfony.com>
 */
final class PunycodeAddressEncoder implements AddressEncoderInterface
{
    /**
     * {@inheritdoc}
     */
    public function encodeString(string $address): string
    {
        $i = strrpos($address, '@');
        if (false !== $i) {
            if (!\defined('INTL_IDNA_VARIANT_UTS46') && preg_match('/[\x80-\xFF]/', $address)) {
                throw new AddressEncoderException(sprintf('Unsupported IDN address "%s", try enabling the "intl" PHP extension or running "composer require symfony/polyfill-intl-idn".', $address));
            }

            $local = substr($address, 0, $i);
            $domain = substr($address, $i + 1);
            $local = \defined('INTL_IDNA_VARIANT_UTS46') ? idn_to_ascii($local, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : $local;
            $domain = \defined('INTL_IDNA_VARIANT_UTS46') ? idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : strtolower($domain);
            if (false === $local || false === $domain) {
                throw new AddressEncoderException(sprintf('Unsupported IDN address "%s".', $address));
            }

            $address = sprintf('%s@%s', $local, $domain);
        }

        return $address;
    }
}
