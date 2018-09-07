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

/**
 * SelfSaltingEncoderInterface is a marker interface for encoders that do not
 * require a user-generated salt.
 *
 * @author Zan Baldwin <hello@zanbaldwin.com>
 */
interface SelfSaltingEncoderInterface
{
}
