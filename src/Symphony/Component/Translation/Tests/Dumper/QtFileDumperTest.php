<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Translation\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Translation\MessageCatalogue;
use Symphony\Component\Translation\Dumper\QtFileDumper;

class QtFileDumperTest extends TestCase
{
    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => 'bar'), 'resources');

        $dumper = new QtFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../fixtures/resources.ts', $dumper->formatCatalogue($catalogue, 'resources'));
    }
}
