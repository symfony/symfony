<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Strategy;

use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * AccessStrategy is the base class for all access strategy classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 */
abstract class AccessStrategy implements AccessStrategyInterface
{
    protected $voters;
    protected $allowIfAllAbstainDecisions;

    /**
     * Constructor.
     *
     * @param VoterInterface[] An array of VoterInterface objects.
     * @param Boolean $allowIfAllAbstainDecisions Allow access if all voters abstain decisions.
     */
    public function __construct(array $voters, $allowIfAllAbstainDecisions = false)
    {
        if (!$voters) {
            throw new \InvalidArgumentException('You must at least add one voter.');
        }

        $this->voters = $voters;
        $this->allowIfAllAbstainDecisions = $allowIfAllAbstainDecisions;
    }

    /**
     * {@inheritdoc}
     */
    public function getVoters()
    {
        return $this->voters;
    }
}