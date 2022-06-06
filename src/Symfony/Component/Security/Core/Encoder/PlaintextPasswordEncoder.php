<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encoder;

use Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use "%s" instead.', PlaintextPasswordEncoder::class, PlaintextPasswordHasher::class);

/**
 * PlaintextPasswordEncoder does not do any encoding but is useful in testing environments.
 *
 * As this encoder is not cryptographically secure, usage of it in production environments is discouraged.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 5.3, use {@link PlaintextPasswordHasher} instead
 */
class PlaintextPasswordEncoder extends BasePasswordEncoder
{
    use LegacyEncoderTrait;

    /**
     * @param bool $ignorePasswordCase Compare password case-insensitive
     */
    public function __construct(bool $ignorePasswordCase = false)
    {
        $this->hasher = new PlaintextPasswordHasher($ignorePasswordCase);
    }
}
