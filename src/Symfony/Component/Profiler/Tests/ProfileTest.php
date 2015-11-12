<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Tests;

use Symfony\Component\Profiler\DataCollector\MemoryDataCollector;
use Symfony\Component\Profiler\Profile;

/**
 * ProfileTest.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class ProfileTest extends \PHPUnit_Framework_TestCase
{
    public function testHoldsProfileData()
    {
        $profile = new Profile('test-profile');

        $collector = new MemoryDataCollector();
        $profile->add($collector->getCollectedData());
        $this->assertTrue($profile->has('memory'));
        $this->assertInstanceof('Symfony\Component\Profiler\ProfileData\ProfileDataInterface', $profile->get('memory'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage ProfileData "memory" does not exist.
     */
    public function testProfileDataCollector()
    {
        $profile = new Profile('test-profile');

        $profile->get('memory');
    }

    public function testNestable()
    {
        $profile = new Profile('test-profile');
        $childProfile = new Profile('test-child');

        $profile->setChildren(array($childProfile));
        $this->assertEquals($profile, $childProfile->getParent());
    }
}
