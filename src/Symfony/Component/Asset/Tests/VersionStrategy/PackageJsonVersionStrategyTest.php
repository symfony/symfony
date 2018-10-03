<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests\VersionStrategy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\VersionStrategy\PackageJsonVersionStrategy;

class PackageJsonVersionStrategyTest extends TestCase
{
    public function testGetVersion()
    {
        $strategy = $this->createStrategy('package-valid.json');

        $this->assertEquals('1.2.3', $strategy->getVersion('main.js'));
    }

    public function testApplyVersion()
    {
        $strategy = $this->createStrategy('package-valid.json');

        $this->assertEquals('css/styles.css?1.2.3', $strategy->applyVersion('css/styles.css'));
    }

    public function testApplyVersionFormat()
    {
        $strategy = $this->createStrategy('package-valid.json', '%s?v=%s');

        $this->assertEquals('css/styles.css?v=1.2.3', $strategy->applyVersion('css/styles.css'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testMissingPackageFileThrowsException()
    {
        $strategy = $this->createStrategy('non-existent-file.json');
        $strategy->getVersion('main.js');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Error parsing JSON
     */
    public function testManifestFileWithBadJSONThrowsException()
    {
        $strategy = $this->createStrategy('package-invalid.json');
        $strategy->getVersion('main.js');
    }

    private function createStrategy($packageJsonFilename, $format = null)
    {
        return new PackageJsonVersionStrategy(__DIR__.'/../fixtures/'.$packageJsonFilename, $format);
    }
}
