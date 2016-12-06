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
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class VoterTest extends BaseVoterTest
{
    protected function getVoter()
    {
        return new VoterTest_Voter();
    }

}

class VoterTest_Voter extends Voter
{
    protected function voteOnAttribute($attribute, $object, TokenInterface $token)
    {
        return 'EDIT' === $attribute;
    }

    protected function supports($attribute, $object)
    {
        return $object instanceof \stdClass && in_array($attribute, array('EDIT', 'CREATE'));
    }
}
