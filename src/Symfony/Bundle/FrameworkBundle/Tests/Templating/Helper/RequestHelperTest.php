<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\RequestHelper;

class RequestHelperTest extends \PHPUnit_Framework_TestCase
{
    protected $request;

    public function setUp()
    {
        $this->request = new Request();
        $this->request->initialize(array('foobar' => 'bar'));
    }

    protected function tearDown()
    {
        $this->request = null;
    }

    public function testGetParameter()
    {
        $helper = new RequestHelper($this->request);

        $this->assertEquals('bar', $helper->getParameter('foobar'));
        $this->assertEquals('foo', $helper->getParameter('bar', 'foo'));

        $this->assertNull($helper->getParameter('foo'));
    }

    public function testGetName()
    {
        $helper = new RequestHelper($this->request);

        $this->assertEquals('request', $helper->getName());
    }
}
