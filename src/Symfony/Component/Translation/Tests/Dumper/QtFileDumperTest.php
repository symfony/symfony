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
use Symfony\Component\Translation\Dumper\QtFileDumper;

class QtFileDumperTest extends TestCase
{
    public function testDump()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => 'bar'), 'resources');

        $tempDir = sys_get_temp_dir();
        $dumper = new QtFileDumper();
        $dumper->dump($catalogue, array('path' => $tempDir));

        $this->assertFileEquals(__DIR__.'/../fixtures/resources.ts', $tempDir.'/resources.en.ts');

        unlink($tempDir.'/resources.en.ts');
    }
}
