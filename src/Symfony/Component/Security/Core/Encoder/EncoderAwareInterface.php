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

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherAwareInterface;

/**
 * @author Christophe Coevoet <stof@notk.org>
 *
 * @deprecated since Symfony 5.3, use {@link PasswordHasherAwareInterface} instead.
 */
interface EncoderAwareInterface
{
    /**
     * Gets the name of the encoder used to encode the password.
     *
     * If the method returns null, the standard way to retrieve the encoder
     * will be used instead.
     *
     * @return string|null
     */
    public function getEncoderName();
}
