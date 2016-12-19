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

use Symfony\Component\Security\Core\Authorization\Voter\AbstractVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AbstractVoterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TokenInterface
     */
    protected $token;

    protected function setUp()
    {
        $this->token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
    }

    /**
     * @return array
     */
    public function getTests()
    {
        return array(
            array(array('EDIT'), VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access'),
            array(array('CREATE'), VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if attribute and class are supported and attribute does not grant access'),

            array(array('DELETE', 'EDIT'), VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute is supported and grants access'),
            array(array('DELETE', 'CREATE'), VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if one attribute is supported and denies access'),

            array(array('CREATE', 'EDIT'), VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute grants access'),

            array(array('DELETE'), VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attribute is supported'),

            array(array('EDIT'), VoterInterface::ACCESS_ABSTAIN, $this, 'ACCESS_ABSTAIN if class is not supported'),

            array(array('EDIT'), VoterInterface::ACCESS_ABSTAIN, null, 'ACCESS_ABSTAIN if object is null'),

            array(array(), VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attributes were provided'),
        );
    }

    /**
     * @dataProvider getTests
     */
    public function testVote(array $attributes, $expectedVote, $object, $message)
    {
        $voter = new AbstractVoterTest_Voter();

        $this->assertEquals($expectedVote, $voter->vote($this->token, $object, $attributes), $message);
    }

    /**
     * @return array
     */
    public function getSupportsAttributeData()
    {
        return array(
            'positive_string_edit' => array(
                'expected' => true,
                'attribute' => 'EDIT',
                'message' => 'expected TRUE given as attribute EDIT is supported',
            ),
            'positive_string_create' => array(
                'expected' => true,
                'attribute' => 'CREATE',
                'message' => 'expected TRUE as given attribute CREATE is supported',
            ),

            'negative_string_read' => array(
                'expected' => false,
                'attribute' => 'READ',
                'message' => 'expected FALSE as given attribute READ is not supported',
            ),
            'negative_string_random' => array(
                'expected' => false,
                'attribute' => 'random',
                'message' => 'expected FALSE as given attribute "random" is not supported',
            ),
            'negative_string_0' => array(
                'expected' => false,
                'attribute' => '0',
                'message' => 'expected FALSE as given attribute "0" is not supported',
            ),
            // this set of data gives false positive if in_array is not used with strict flag set to 'true'
            'negative_int_0' => array(
                'expected' => false,
                'attribute' => 0,
                'message' => 'expected FALSE as given attribute 0 is not string',
            ),
            'negative_int_1' => array(
                'expected' => false,
                'attribute' => 1,
                'message' => 'expected FALSE as given attribute 1 is not string',
            ),
            'negative_int_7' => array(
                'expected' => false,
                'attribute' => 7,
                'message' => 'expected FALSE as attribute 7 is not string',
            ),
        );
    }

    /**
     * @dataProvider getSupportsAttributeData
     *
     * @param bool   $expected
     * @param string $attribute
     * @param string $message
     */
    public function testSupportsAttribute($expected, $attribute, $message)
    {
        $voter = new AbstractVoterTest_Voter();

        $this->assertEquals($expected, $voter->supportsAttribute($attribute), $message);
    }
}

class AbstractVoterTest_Voter extends AbstractVoter
{
    protected function getSupportedClasses()
    {
        return array('stdClass');
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
