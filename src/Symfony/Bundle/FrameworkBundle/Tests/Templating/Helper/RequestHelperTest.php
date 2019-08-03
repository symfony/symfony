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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\RequestHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @group legacy
 */
class RequestHelperTest extends TestCase
{
    protected $requestStack;

    protected function setUp()
    {
        $this->requestStack = new RequestStack();
        $request = new Request();
        $request->initialize(['foobar' => 'bar']);
        $this->requestStack->push($request);
    }

    public function testGetParameter()
    {
        $helper = new RequestHelper($this->requestStack);

        $this->assertEquals('bar', $helper->getParameter('foobar'));
        $this->assertEquals('foo', $helper->getParameter('bar', 'foo'));

        $this->assertNull($helper->getParameter('foo'));
    }

    public function testGetLocale()
    {
        $helper = new RequestHelper($this->requestStack);

        $this->assertEquals('en', $helper->getLocale());
    }

    public function testGetName()
    {
        $helper = new RequestHelper($this->requestStack);

        $this->assertEquals('request', $helper->getName());
    }
}
