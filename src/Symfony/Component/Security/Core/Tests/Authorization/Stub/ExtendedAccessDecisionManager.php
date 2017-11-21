<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization\Stub;

use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;

class ExtendedAccessDecisionManager extends AccessDecisionManager
{
    private $voters;
    private $strategy;

    public function __construct($voters = array(), $strategy = self::STRATEGY_AFFIRMATIVE, $allowIfAllAbstainDecisions = false, $allowIfEqualGrantedDeniedDecisions = true)
    {
        $strategyMethod = 'decide'.ucfirst($strategy);

        $this->voters = $voters;
        $this->strategy = $strategyMethod;
        $this->allowIfAllAbstainDecisions = (bool) $allowIfAllAbstainDecisions;
        $this->allowIfEqualGrantedDeniedDecisions = (bool) $allowIfEqualGrantedDeniedDecisions;
    }
}
