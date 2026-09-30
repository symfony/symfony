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

class Vote
{
    /**
     * @var class-string<VoterInterface>|string
     */
    public string $voter;

    /**
     * @var VoterInterface::ACCESS_*
     */
    public int $result;

    /**
     * @var list<string>
     */
    public array $reasons = [];

    /**
     * @var array<string, mixed>
     */
    public array $extraData = [];

    /**
     * The denied attribute a fresh authentication could grant, see {@see requestReAuthentication()}.
     */
    public private(set) ?string $reAuthenticationAttribute = null;

    public function addReason(string $reason): void
    {
        $this->reasons[] = $reason;
    }

    /**
     * Tells the firewall that a fresh authentication could grant the attribute this vote denies.
     *
     * The firewall then starts a re-authentication rather than answering with a 403.
     * With a strategy other than "affirmative", another voter denying the same attribute can leave the user denied after re-authenticating.
     */
    public function requestReAuthentication(string $attribute): void
    {
        $this->reAuthenticationAttribute = $attribute;
    }
}
