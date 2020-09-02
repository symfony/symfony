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

/**
 * An IDN email address encoder.
 *
 * Encodes the domain part of an address using IDN. This is compatible will all
 * SMTP servers.
 *
 * Note: It leaves the local part as is. In case there are non-ASCII characters
 * in the local part then it depends on the SMTP Server if this is supported.
 *
 * @author Christian Schmidt
 */
final class IdnAddressEncoder implements AddressEncoderInterface
{
    /**
     * Encodes the domain part of an address using IDN.
     */
    public function encodeString(string $address): string
    {
        $i = strrpos($address, '@');
        if (false !== $i) {
            $local = substr($address, 0, $i);
            $domain = substr($address, $i + 1);

            if (preg_match('/[^\x00-\x7F]/', $domain)) {
                $address = sprintf('%s@%s', $local, idn_to_ascii($domain, 0, \INTL_IDNA_VARIANT_UTS46));
            }
        }

        return $address;
    }
}
