<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Translation\Dumper;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Dumper\XliffDumper;

class XliffFileDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDump()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => 'bar'));
        
        $dumper = new XliffDumper();
        $dumperString = $dumper->dump($catalogue);
        
        $resource = __DIR__.'/../fixtures/resources.xliff';
        $file = new \SplFileObject($resource);
        $fileString = '';
        while(!$file->eof()) {
            $fileString .= $file->fgets();
        }
        
        $this->assertEquals($fileString, $dumperString);
    }
}
