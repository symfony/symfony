<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Util;

/**
 * A secure random number generator implementation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
final class SecureRandom implements SecureRandomInterface
{
    /**
     * {@inheritdoc}
     */
    public function nextBytes($nbBytes)
    {
        return random_bytes($nbBytes);
    }
}
