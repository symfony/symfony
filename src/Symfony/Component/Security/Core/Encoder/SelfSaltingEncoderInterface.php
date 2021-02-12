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

trigger_deprecation('symfony/security-core', '5.3', sprintf('The "%s" interface is deprecated, use "%s" on hasher implementations that deal with salts instead.', SelfSaltingEncoderInterface::class, LegacyPasswordHasherInterface::class));

use Symfony\Component\PasswordHasher\LegacyPasswordHasherInterface;

/**
 * SelfSaltingEncoderInterface is a marker interface for encoders that do not
 * require a user-generated salt.
 *
 * @author Zan Baldwin <hello@zanbaldwin.com>
 *
 * @deprecated since Symfony 5.3, use {@link LegacyPasswordHasherInterface} instead
 */
interface SelfSaltingEncoderInterface
{
}
