<?php

namespace Symfony\Bundle\FrameworkBundle\Security\Authorization;

use Symfony\Component\Security\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Authorization\AccessDecisionManager as BaseAccessDecisionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * 
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AccessDecisionManager extends BaseAccessDecisionManager
{
    /**
     * Constructor.
     *
     * @param VoterInterface[] $voters                     An array of VoterInterface instances
     * @param string           $strategy                   The vote strategy
     * @param Boolean          $allowIfAllAbstainDecisions Whether to grant access if all voters abstained or not
     */
    public function __construct(ContainerInterface $container, $strategy = 'affirmative', $allowIfAllAbstainDecisions = false, $allowIfEqualGrantedDeniedDecisions = true)
    {
        parent::__construct(array(), $strategy, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions);

        $this->voters = array();
        foreach ($container->findTaggedServiceIds('security.voter') as $id => $attributes) {
            $this->voters[] = $container->get($id);
        }
    }
}
