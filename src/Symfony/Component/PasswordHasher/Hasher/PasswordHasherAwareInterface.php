<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Hasher;

/**
 * @author Christophe Coevoet <stof@notk.org>
 */
interface PasswordHasherAwareInterface
{
    /**
     * Gets the name of the password hasher used to hash the password.
     *
     * If the method returns null, the standard way to retrieve the password hasher
     * will be used instead.
     */
    public function getPasswordHasherName(): ?string;
}
