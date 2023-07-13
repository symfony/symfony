<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\FileTransport;

class FileTransportTest extends TestCase
{
    public function testToString()
    {
        $file = sys_get_temp_dir().'/file.txt';
        $dsn = new Dsn('file', 'null', null, null, null, [], $file);
        $t = new FileTransport($dsn);
        $this->assertEquals('file://'.$file, (string) $t);
    }
}
