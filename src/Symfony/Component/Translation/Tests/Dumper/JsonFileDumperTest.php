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

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Dumper\JsonFileDumper;

class JsonFileDumperTest extends \PHPUnit_Framework_TestCase
{
    private $tempDir;

    protected function setUp()
    {
        $this->tempDir = sys_get_temp_dir();
    }

    protected function tearDown()
    {
        unlink($this->tempDir.'/messages.en.json');
    }

    public function testDump()
    {
        if (PHP_VERSION_ID < 50400) {
            $this->markTestIncomplete('PHP below 5.4 doesn\'t support JSON pretty printing');
        }

        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => 'bar'));

        $dumper = new JsonFileDumper();
        $dumper->dump($catalogue, array('path' => $this->tempDir));

        $this->assertEquals(file_get_contents(__DIR__.'/../fixtures/resources.json'), file_get_contents($this->tempDir.'/messages.en.json'));
    }

    public function testDumpWithCustomEncoding()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => '"bar"'));

        $dumper = new JsonFileDumper();
        $dumper->dump($catalogue, array('path' => $this->tempDir, 'json_encoding' => JSON_HEX_QUOT));

        $this->assertEquals(file_get_contents(__DIR__.'/../fixtures/resources.dump.json'), file_get_contents($this->tempDir.'/messages.en.json'));
    }
}
