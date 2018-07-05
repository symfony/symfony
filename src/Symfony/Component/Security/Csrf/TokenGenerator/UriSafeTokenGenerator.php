<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\TokenGenerator;

use Symfony\Component\Security\Core\Util\SecureRandomInterface;

/**
 * Generates CSRF tokens.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class UriSafeTokenGenerator implements TokenGeneratorInterface
{
    private $entropy;

    /**
     * Generates URI-safe CSRF tokens.
     *
     * @param int $entropy The amount of entropy collected for each token (in bits)
     */
    public function __construct($entropy = 256)
    {
        if ($entropy instanceof SecureRandomInterface || 2 === \func_num_args()) {
            @trigger_error('The '.__METHOD__.' method now requires the entropy to be given as the first argument. The SecureRandomInterface will be removed in 3.0.', E_USER_DEPRECATED);

            $this->entropy = 2 === \func_num_args() ? func_get_arg(1) : 256;
        } else {
            $this->entropy = $entropy;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateToken()
    {
        // Generate an URI safe base64 encoded string that does not contain "+",
        // "/" or "=" which need to be URL encoded and make URLs unnecessarily
        // longer.
        $bytes = random_bytes($this->entropy / 8);

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
