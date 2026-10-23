<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Attribute;

/**
 * Requires the request to carry a Privacy Pass token, and challenges it for one otherwise.
 *
 * The token proves that a client was able to get one issued, nothing else: it names no user, and cannot be linked to the interaction it was issued for.
 *
 * A request carrying no acceptable token is answered with a challenge, and the controller does not run.
 * When the token is optional, the controller runs instead and reads the verdict from the "_security.private_token_valid" request attribute, so that it can answer with a fallback such as a CAPTCHA; the challenge is then carried by the response it returns.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9577
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class RequiresPrivateToken
{
    /** @var string[] */
    public readonly array $methods;

    /**
     * @param string          $name     The name of the configured set of issuers and challenges to use
     * @param string[]|string $methods  The HTTP methods a token is required for; empty means all of them
     * @param bool            $optional Whether the controller runs for a request carrying no acceptable token, its successful response turned into a challenge
     */
    public function __construct(
        public readonly string $name = 'default',
        array|string $methods = [],
        public readonly bool $optional = false,
    ) {
        if (\in_array('GET', $methods = array_map('strtoupper', (array) $methods), true)) {
            $methods[] = 'HEAD';
        }

        $this->methods = $methods;
    }
}
