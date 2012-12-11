<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Doctrine\Logger;

class DbalLoggerTest extends \PHPUnit_Framework_TestCase
{
    public function testLogNonUtf8()
    {
        $logger = $this->getMock('Symfony\\Component\\HttpKernel\\Log\\LoggerInterface');

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs(array($logger, null))
            ->setMethods(array('log'))
            ->getMock()
        ;

        $dbalLogger
            ->expects($this->once())
            ->method('log')
            ->with('SQL ({"utf8":"foo","nonutf8":null})')
        ;

        $dbalLogger->startQuery('SQL', array(
            'utf8'    => 'foo',
            'nonutf8' => "\x7F\xFF"
        ));
    }
}
