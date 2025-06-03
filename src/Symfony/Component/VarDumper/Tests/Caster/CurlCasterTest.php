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
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @requires extension curl
 */
class CurlCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testCastCurl()
    {
        $ch = curl_init('http://example.com');
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
CurlHandle {
  url: "http://example.com/"
  content_type: "text/html"
  http_code: %d
%A
}
EODUMP, $ch);
    }
}
