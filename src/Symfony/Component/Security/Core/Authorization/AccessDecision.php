<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Contains the access verdict and all the related votes.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 * @author Roman JOLY <eltharin18@outlook.fr>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AccessDecision
{
    /**
     * @var class-string<AccessDecisionStrategyInterface>|string|null
     */
    public ?string $strategy = null;

    public bool $isGranted;

    /**
     * @var Vote[]
     */
    public array $votes = [];

    public function getMessage(): string
    {
        $message = $this->isGranted ? 'Access Granted.' : 'Access Denied.';
        $access = $this->isGranted ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED;

        if ($this->votes) {
            foreach ($this->votes as $vote) {
                if ($vote->result !== $access) {
                    continue;
                }
                foreach ($vote->reasons as $reason) {
                    $message .= ' '.$reason;
                }
            }
        }

        return $message;
    }
}
