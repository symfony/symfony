<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Tests\Fixtures\DataCollector\CloneVarDataCollector;
use Symphony\Component\VarDumper\Cloner\VarCloner;

class DataCollectorTest extends TestCase
{
    public function testCloneVarStringWithScheme()
    {
        $c = new CloneVarDataCollector('scheme://foo');
        $c->collect(new Request(), new Response());
        $cloner = new VarCloner();

        $this->assertEquals($cloner->cloneVar('scheme://foo'), $c->getData());
    }

    public function testCloneVarExistingFilePath()
    {
        $c = new CloneVarDataCollector(array($filePath = tempnam(sys_get_temp_dir(), 'clone_var_data_collector_')));
        $c->collect(new Request(), new Response());

        $this->assertSame($filePath, $c->getData()[0]);
    }
}
