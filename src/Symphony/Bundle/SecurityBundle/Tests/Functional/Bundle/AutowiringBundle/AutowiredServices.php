<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\AutowiringBundle;

use Symphony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

class AutowiredServices
{
    private $accessDecisionManager;

    public function __construct(AccessDecisionManagerInterface $accessDecisionManager)
    {
        $this->accessDecisionManager = $accessDecisionManager;
    }

    public function getAccessDecisionManager()
    {
        return $this->accessDecisionManager;
    }
}
