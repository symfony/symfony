<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DataCollector\DotenvDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DotenvDataCollectorTest extends TestCase
{
    public function testCollect()
    {
        putenv('SYMFONY_DOTENV_VARS=APP_DEBUG,DATABASE_URL,DELETED_VAR');

        putenv('APP_DEBUG=1');
        putenv('DATABASE_URL=sqlite:///var/data/db.sqlite');
        putenv('DELETED_VAR');

        $collector = new DotenvDataCollector();
        $collector->collect(new Request(), new Response());

        $this->assertEquals(array('APP_DEBUG' => '1', 'DATABASE_URL' => 'sqlite:///var/data/db.sqlite'), $collector->getEnvs());
    }

    public function testSpecialVariableNotExists()
    {
        putenv('SYMFONY_DOTENV_VARS');

        $collector = new DotenvDataCollector();
        $collector->collect(new Request(), new Response());

        $this->assertEmpty($collector->getEnvs());
    }
}
