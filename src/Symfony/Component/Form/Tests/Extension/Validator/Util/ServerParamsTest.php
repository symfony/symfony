<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\Util\ServerParams;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ServerParamsTest extends TestCase
{
    public function testGetContentLengthFromSuperglobals()
    {
        $serverParams = new ServerParams();
        $this->assertNull($serverParams->getContentLength());

        $_SERVER['CONTENT_LENGTH'] = 1024;

        $this->assertEquals(1024, $serverParams->getContentLength());

        unset($_SERVER['CONTENT_LENGTH']);
    }

    public function testGetContentLengthFromRequest()
    {
        $request = Request::create('http://foo', 'GET', [], [], [], ['CONTENT_LENGTH' => 1024]);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $serverParams = new ServerParams($requestStack);

        $this->assertEquals(1024, $serverParams->getContentLength());
    }

    /** @dataProvider getGetPostMaxSizeTestData */
    public function testGetPostMaxSize($size, $bytes)
    {
        $serverParams = new DummyServerParams($size);

        $this->assertEquals($bytes, $serverParams->getPostMaxSize());
    }

    public function getGetPostMaxSizeTestData()
    {
        return [
            ['2k', 2048],
            ['2 k', 2048],
            ['8m', 8 * 1024 * 1024],
            ['+2 k', 2048],
            ['+2???k', 2048],
            ['0x10', 16],
            ['0xf', 15],
            ['010', 8],
            ['+0x10 k', 16 * 1024],
            ['1g', 1024 * 1024 * 1024],
            ['-1', -1],
            ['0', 0],
            ['2mk', 2048], // the unit must be the last char, so in this case 'k', not 'm'
        ];
    }
}

class DummyServerParams extends ServerParams
{
    private $size;

    public function __construct($size)
    {
        parent::__construct();

        $this->size = $size;
    }

    public function getNormalizedIniPostMaxSize()
    {
        return $this->size;
    }
}
