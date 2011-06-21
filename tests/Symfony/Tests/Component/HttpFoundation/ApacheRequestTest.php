<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\ApacheRequest;

class ApacheRequestTest extends \PHPUnit_Framework_TestCase
{
    public function testGetBaseUrlDoesNotForceScriptName()
    {
        $request = new ApacheRequest();
        $request->server->replace(array(
            'REQUEST_URI' => '/foo/bar',
            'SCRIPT_NAME' => '/index.php',
        ));

        $this->assertEquals('', $request->getBaseUrl(), '->getBaseUrl() does not add the script name');
    }

    public function testGetBaseUrlIncludesScriptName()
    {
        $request = new ApacheRequest();
        $request->server->replace(array(
            'REQUEST_URI' => '/index.php/foo/bar',
            'SCRIPT_NAME' => '/index.php',
        ));

        $this->assertEquals('/index.php', $request->getBaseUrl(), '->getBaseUrl() includes the script name');
    }
}
