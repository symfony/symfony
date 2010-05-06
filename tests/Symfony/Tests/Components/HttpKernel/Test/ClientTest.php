<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\HttpKernel\Test;

use Symfony\Components\HttpKernel\Test\Client;
use Symfony\Components\HttpKernel\Test\RequestTester;
use Symfony\Components\HttpKernel\Test\ResponseTester;
use Symfony\Components\HttpKernel\HttpKernel;
use Symfony\Components\HttpKernel\Request;
use Symfony\Components\HttpKernel\Response;
use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\EventDispatcher\Event;

require_once __DIR__.'/TestHttpKernel.php';

class TestClient extends Client
{
    protected function getScript($request)
    {
        $script = parent::getScript($request);

        $script = preg_replace('/(\->register\(\);)/', "$0\nrequire_once '".__DIR__."/TestHttpKernel.php';", $script);

        return $script;
    }
}

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testDoRequest()
    {
        $client = new Client(new TestHttpKernel());

        $client->request('GET', '/');
        $this->assertEquals('Request: /', $client->getResponse()->getContent(), '->doRequest() uses the request handler to make the request');

        $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('Request: /', $client->getResponse()->getContent(), '->doRequest() uses the request handler to make the request');
        $this->assertEquals('www.example.com', $client->getRequest()->getHost(), '->doRequest() uses the request handler to make the request');
    }

    public function testGetScript()
    {
        $client = new TestClient(new TestHttpKernel());
        $client->insulate();
        $client->request('GET', '/');

        $this->assertEquals('Request: /', $client->getResponse()->getContent(), '->getScript() returns a script that uses the request handler to make the request');
    }

    public function testAddHasGetTester()
    {
        $client = new TestClient(new TestHttpKernel());
        $client->request('GET', '/');
        $client->addTester('foo', $tester = new RequestTester($client->getRequest()));

        $this->assertSame($tester, $client->getTester('foo'), '->addTester() adds a tester object');

        try
        {
            $client->getTester('bar');
            $this->pass('->getTester() throws an \InvalidArgumentException if the tester object does not exist');
        }
        catch (\Exception $e)
        {
            $this->assertInstanceof('InvalidArgumentException', $e, '->getTester() throws an \InvalidArgumentException if the tester object does not exist');
        }

        $this->assertTrue($client->hasTester('foo'), '->hasTester() returns true if the tester object exist');
        $this->assertFalse($client->hasTester('bar'), '->hasTester() returns false if the tester object does not exist');
    }

    public function testMagicCall()
    {
        $client = new TestClient(new TestHttpKernel());
        $client->request('DELETE', '/foo');
        $client->addTester('request', new RequestTester($client->getRequest()));
        $client->addTester('response', new ResponseTester($client->getResponse()));
        $client->setTestCase($this);

        $client->assertRequestMethod('DELETE');
        $client->assertTrue(true, '->__call() redirects assert methods to PHPUnit');

        try
        {
            $client->foobar();
            $this->pass('->__call() throws a \BadMethodCallException if the method does not exist');
        }
        catch (\Exception $e)
        {
            $this->assertInstanceof('BadMethodCallException', $e, '->__call() throws a \BadMethodCallException if the method does not exist');
        }

        try
        {
            $client->assertFoo();
            $this->pass('->__call() throws a \BadMethodCallException if the method does not exist');
        }
        catch (\Exception $e)
        {
            $this->assertInstanceof('BadMethodCallException', $e, '->__call() throws a \BadMethodCallException if the method does not exist');
        }

        try
        {
            $client->assertFooBar();
            $this->pass('->__call() throws a \BadMethodCallException if the method does not exist');
        }
        catch (\Exception $e)
        {
            $this->assertInstanceof('BadMethodCallException', $e, '->__call() throws a \BadMethodCallException if the method does not exist');
        }
    }
}
