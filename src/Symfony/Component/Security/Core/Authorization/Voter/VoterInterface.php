<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * VoterInterface is the interface implemented by all voters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface VoterInterface
{
    public const ACCESS_GRANTED = 1;
    public const ACCESS_ABSTAIN = 0;
    public const ACCESS_DENIED = -1;

    /**
     * Returns the vote for the given parameters.
     *
     * This method must return one of the following constants:
     * ACCESS_GRANTED, ACCESS_DENIED, or ACCESS_ABSTAIN.
     *
     * @param mixed $subject    The subject to secure
     * @param array $attributes An array of attributes associated with the method being invoked
     *
     * @return int either ACCESS_GRANTED, ACCESS_ABSTAIN, or ACCESS_DENIED
     *
     * @psalm-return self::ACCESS_* must be transformed into @return on Symfony 7
     */
    public function vote(TokenInterface $token, mixed $subject, array $attributes);
}
