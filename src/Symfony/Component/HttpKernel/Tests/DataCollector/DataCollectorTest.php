<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Tests\Fixtures\DataCollector\CloneVarDataCollector;
use Symfony\Component\VarDumper\Cloner\VarCloner;

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
        $c = new CloneVarDataCollector([$filePath = tempnam(sys_get_temp_dir(), 'clone_var_data_collector_')]);
        $c->collect(new Request(), new Response());

        $this->assertSame($filePath, $c->getData()[0]);
    }
}
