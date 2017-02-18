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
use Symfony\Component\Translation\Dumper\XliffFileDumper;

class XliffFileDumperTest extends TestCase
{
    public function testDump()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add(array(
            'foo' => 'bar',
            'key' => '',
            'key.with.cdata' => '<source> & <target>',
        ));
        $catalogue->setMetadata('foo', array('notes' => array(array('priority' => 1, 'from' => 'bar', 'content' => 'baz'))));
        $catalogue->setMetadata('key', array('notes' => array(array('content' => 'baz'), array('content' => 'qux'))));

        $tempDir = sys_get_temp_dir();
        $dumper = new XliffFileDumper();
        $dumper->dump($catalogue, array('path' => $tempDir, 'default_locale' => 'fr_FR'));

        $this->assertEquals(
            file_get_contents(__DIR__.'/../fixtures/resources-clean.xlf'),
            file_get_contents($tempDir.'/messages.en_US.xlf')
        );

        unlink($tempDir.'/messages.en_US.xlf');
    }
}
