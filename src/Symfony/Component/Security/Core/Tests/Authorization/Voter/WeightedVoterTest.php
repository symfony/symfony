<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\WeightedVoter;

class WeightedVoterTest extends BaseVoterTest
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

        $this->assertEquals(4, $voter->getWeight());
    }

    protected function getVoter()
    {
        return new WeightedVoterTest_Voter();
    }

}

class WeightedVoterTest_Voter extends WeightedVoter
{
    protected function voteOnAttribute($attribute, $object, TokenInterface $token)
    {
        return 'EDIT' === $attribute;
    }

    protected function supports($attribute, $object)
    {
        return $object instanceof \stdClass && in_array($attribute, array('EDIT', 'CREATE'));
    }

    public function getWeight()
    {
        return 4;
    }
}
