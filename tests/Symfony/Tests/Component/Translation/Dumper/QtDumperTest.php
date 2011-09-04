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
use Symfony\Component\Translation\Dumper\QtTranslationsDumper;

class QtTranslationsDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDump()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => 'bar'), 'resources');

        $dumper = new QtTranslationsDumper();
        $dumperString = $dumper->dump($catalogue, 'resources');

        $this->assertEquals(file_get_contents(__DIR__.'/../fixtures/resources.ts'), $dumperString);
    }
}