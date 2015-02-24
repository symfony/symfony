<?php

namespace Symfony\Component\Translation\Tests\Util;

use Symfony\Component\Translation\Util\XliffUtils;

class XliffUtilsTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadResource()
    {
        $resource = __DIR__.'/../fixtures/resources.xlf';
        $result = XliffUtils::loadFile($resource);

        $this->assertResultOk($result);
    }

    public function testLoadResourceWithSchema()
    {
        $resource = __DIR__.'/../fixtures/resources.xlf';
        $result = XliffUtils::loadFile($resource, function (\DOMDocument $dom) {
            return true;
        });

        $this->assertResultOk($result);
    }

    /**
     * @expectedException \Symfony\Component\Translation\Exception\InvalidResourceException
     */
    public function testLoadInvalidResource()
    {
        $resource = __DIR__.'/../fixtures/resources.php';
        XliffUtils::loadFile($resource.'resources.php');
    }

    private function assertResultOk($result)
    {
        $this->assertInstanceOf('\DomDocument', $result);
        $this->assertSame(array(), libxml_get_errors());
    }
}
