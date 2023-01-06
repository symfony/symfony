<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

final class SymfonyCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testCastUuid()
    {
        $uuid = new UuidV4('83a9db35-3c8c-4040-b3c1-02eccc00b419');
        $expectedDump = <<<EODUMP
Symfony\Component\Uid\UuidV4 {
  #uid: "83a9db35-3c8c-4040-b3c1-02eccc00b419"
  toBase58: "HFzAAuYvev42cCjwqpnKqz"
  toBase32: "43N7DKAF4C810B7G82XK601D0S"
}
EODUMP;
        $this->assertDumpEquals($expectedDump, $uuid);

        $uuid = new UuidV6('1ebc50e9-8a23-6704-ad6f-59afd5cda7e5');
        if (method_exists($uuid, 'getDateTime')) {
            $expectedDump = <<<EODUMP
Symfony\Component\Uid\UuidV6 {
  #uid: "1ebc50e9-8a23-6704-ad6f-59afd5cda7e5"
  toBase58: "4o8c5m6v4L8h5teww36JDa"
  toBase32: "0YQH8EK2H3CW2ATVTSNZAWV9Z5"
  time: "2021-06-04 08:26:44.591386 UTC"
}
EODUMP;
        } else {
            $expectedDump = <<<EODUMP
Symfony\Component\Uid\UuidV6 {
  #uid: "1ebc50e9-8a23-6704-ad6f-59afd5cda7e5"
  toBase58: "4o8c5m6v4L8h5teww36JDa"
  toBase32: "0YQH8EK2H3CW2ATVTSNZAWV9Z5"
}
EODUMP;
        }

        $this->assertDumpEquals($expectedDump, $uuid);
    }

    public function testCastUlid()
    {
        $ulid = new Ulid('01F7B252SZQGTSQGYSGACASAW6');
        if (method_exists($ulid, 'getDateTime')) {
            $expectedDump = <<<EODUMP
Symfony\Component\Uid\Ulid {
  #uid: "01F7B252SZQGTSQGYSGACASAW6"
  toBase58: "1Ba6pJPFWDwghSKFVvfQ1B"
  toRfc4122: "0179d622-8b3f-bc35-9bc3-d98298acab86"
  time: "2021-06-04 08:27:38.687 UTC"
}
EODUMP;
        } else {
            $expectedDump = <<<EODUMP
Symfony\Component\Uid\Ulid {
  #uid: "01F7B252SZQGTSQGYSGACASAW6"
  toBase58: "1Ba6pJPFWDwghSKFVvfQ1B"
  toRfc4122: "0179d622-8b3f-bc35-9bc3-d98298acab86"
}
EODUMP;
        }

        $this->assertDumpEquals($expectedDump, $ulid);
    }
}
