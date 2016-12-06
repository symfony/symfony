<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization\Voter\Decorator;

use Symfony\Component\Security\Core\Authorization\Voter\Decorator\Weight;
use Symfony\Component\Security\Core\Tests\Authorization\Voter\BaseVoterTest;
use Symfony\Component\Security\Core\Tests\Authorization\Voter\VoterTest_Voter;

class WeightTest extends BaseVoterTest
{
    public function testInterface()
    {
        $voter = $this->getVoter();

        $this->assertInstanceOf('\Symfony\Component\Security\Core\Authorization\Voter\WeightedVoterInterface', $voter);
        $this->assertInstanceOf('\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface', $voter);
    }

    public function testWeight()
    {
        $voter = $this->getVoter();

        $this->assertEquals(3, $voter->getWeight());
    }

    protected function getVoter()
    {
        $baseVoter = new VoterTest_Voter();

        return new Weight($baseVoter, 3);
    }
}
