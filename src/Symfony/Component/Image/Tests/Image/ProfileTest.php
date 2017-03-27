<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Image;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Image\Profile;
use Symfony\Component\Image\Tests\TestCase;

class ProfileTest extends TestCase
{
    public function testName()
    {
        $profile = new Profile('romain', 'neutron');
        $this->assertEquals('romain', $profile->name());
    }

    public function testData()
    {
        $profile = new Profile('romain', 'neutron');
        $this->assertEquals('neutron', $profile->data());
    }

    public function testFromPath()
    {
        $file = FixturesLoader::getFixture('ICCProfiles/Adobe/CMYK/JapanColor2001Uncoated.icc');
        $profile = Profile::fromPath($file);

        $this->assertEquals(basename($file), $profile->name());
        $this->assertEquals(file_get_contents($file), $profile->data());
    }

    /**
     * @expectedException \Symfony\Component\Image\Exception\InvalidArgumentException
     */
    public function testFromInvalidPath()
    {
        $file = __DIR__ . '/non-existent-profile.icc';
        Profile::fromPath($file);
    }
}
