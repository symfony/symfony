<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Dumper\IcuResFileDumper;

class IcuResFileDumperTest extends TestCase
{
    /**
     * @requires extension mbstring
     */
    public function testDump()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => 'bar'));

        $tempDir = sys_get_temp_dir().'/IcuResFileDumperTest';
        $dumper = new IcuResFileDumper();
        $dumper->dump($catalogue, array('path' => $tempDir));

        $this->assertFileEquals(__DIR__.'/../fixtures/resourcebundle/res/en.res', $tempDir.'/messages/en.res');

        @unlink($tempDir.'/messages/en.res');
        @rmdir($tempDir.'/messages');
        @rmdir($tempDir);
    }
}
