<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Dsn;

/**
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 */
class DsnTest extends TestCase
{
    public function testIsValid()
    {
        $this->assertTrue(Dsn::isValid('redis://elsa:secret@localhost:6321/1?test=1'));
    }

    public function testIsNotValid()
    {
        $this->assertFalse(Dsn::isValid('gerard:////'));
    }

    public function testFromString()
    {
        $parsedDsn = Dsn::fromString('redis://elsa:secret@localhost:6321/1?test=1', []);
        $this->assertSame($parsedDsn->getScheme(), 'redis');
        $this->assertSame($parsedDsn->getHost(), 'localhost');
        $this->assertSame($parsedDsn->getUser(), 'elsa');
        $this->assertSame($parsedDsn->getPassword(), 'secret');
        $this->assertSame($parsedDsn->getPort(), 6321);
        $this->assertSame($parsedDsn->getPath(), '/1');
        $this->assertSame($parsedDsn->getOption('test'), '1');
    }
}
