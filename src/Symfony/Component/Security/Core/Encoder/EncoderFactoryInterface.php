<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encoder;

use Symfony\Component\Security\Core\User\AccountInterface;

/**
 * EncoderFactoryInterface to support different encoders for different accounts.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface EncoderFactoryInterface
{
    /**
     * Returns the password encoder to use for the given account
     *
     * @param AccountInterface $account
     * @return PasswordEncoderInterface never null
     */
    function getEncoder(AccountInterface $account);
}