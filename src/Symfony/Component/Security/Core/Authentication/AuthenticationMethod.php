<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The authentication methods a token can hold a proof of, named as RFC 8176 registers them.
 *
 * They key the map TokenInterface::getAuthenticationProofs() returns, so that a trust resolver
 * can require a specific one; the values are the "amr" claim of OpenID Connect, which a provider
 * may extend beyond this list.
 *
 * @see TokenInterface::getAuthenticationProofs()
 * @see https://www.rfc-editor.org/rfc/rfc8176.html#section-2
 */
final class AuthenticationMethod
{
    /**
     * A proof whose method the authenticator did not state; not a registered value.
     */
    public const UNSPECIFIED = '*';

    /**
     * Facial recognition.
     */
    public const FACE = 'face';

    /**
     * Fingerprint biometric.
     */
    public const FINGERPRINT = 'fpt';

    /**
     * Use of geolocation information.
     */
    public const GEOLOCATION = 'geo';

    /**
     * Proof-of-possession of a hardware-secured key.
     */
    public const HARDWARE_KEY = 'hwk';

    /**
     * Iris scan biometric.
     */
    public const IRIS = 'iris';

    /**
     * Knowledge-based authentication.
     */
    public const KNOWLEDGE_BASED = 'kba';

    /**
     * Multiple-channel authentication, e.g. confirming a login over another channel.
     */
    public const MULTIPLE_CHANNELS = 'mca';

    /**
     * Multiple-factor authentication, when the provider names no individual factor.
     */
    public const MULTIPLE_FACTORS = 'mfa';

    /**
     * One-time password, e.g. a TOTP.
     */
    public const ONE_TIME_PASSWORD = 'otp';

    /**
     * Personal identification number or pattern.
     */
    public const PIN = 'pin';

    /**
     * Password-based authentication.
     */
    public const PASSWORD = 'pwd';

    /**
     * Risk-based authentication.
     */
    public const RISK_BASED = 'rba';

    /**
     * Retina scan biometric.
     */
    public const RETINA = 'retina';

    /**
     * Smart card.
     */
    public const SMART_CARD = 'sc';

    /**
     * Confirmation by SMS text message.
     */
    public const SMS = 'sms';

    /**
     * Proof-of-possession of a software-secured key.
     */
    public const SOFTWARE_KEY = 'swk';

    /**
     * Confirmation by telephone call.
     */
    public const TELEPHONE_CALL = 'tel';

    /**
     * User presence test.
     */
    public const USER_PRESENCE = 'user';

    /**
     * Voice biometric.
     */
    public const VOICE = 'vbm';

    /**
     * Windows integrated authentication.
     */
    public const WINDOWS_INTEGRATED = 'wia';
}
