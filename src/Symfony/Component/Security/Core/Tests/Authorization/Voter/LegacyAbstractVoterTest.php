<?php

namespace Symfony\Component\Security\Core\Tests\Authorization\Voter;

use Symfony\Component\Security\Core\Authorization\Voter\AbstractVoter;

class LegacyAbstractVoterTest_Voter extends AbstractVoter
{
    protected function getSupportedClasses()
    {
        return array('AbstractVoterTest_Object');
    }

    protected function getSupportedAttributes()
    {
        return array('EDIT', 'CREATE');
    }

    protected function isGranted($attribute, $object, $user = null)
    {
        return 'EDIT' === $attribute;
    }
}

class LegacyAbstractVoterTest extends AbstractVoterTest
{
    protected function setUp()
    {
        parent::setUp();

        $this->voter = new LegacyAbstractVoterTest_Voter();
    }
}
