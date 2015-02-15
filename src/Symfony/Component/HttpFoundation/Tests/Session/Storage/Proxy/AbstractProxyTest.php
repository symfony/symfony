<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Proxy;

use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;

// Note until PHPUnit_Mock_Objects 1.2 is released you cannot mock abstracts due to
// https://github.com/sebastianbergmann/phpunit-mock-objects/issues/73
class ConcreteProxy extends AbstractProxy
{
}

class ConcreteSessionHandlerInterfaceProxy extends AbstractProxy implements \SessionHandlerInterface
{
    public function open($savePath, $sessionName)
    {
    }

    public function close()
    {
    }

    public function read($id)
    {
    }

    public function write($id, $data)
    {
    }

    public function destroy($id)
    {
    }

    public function gc($maxlifetime)
    {
    }
}

/**
 * Test class for AbstractProxy.
 *
 * @author Drak <drak@zikula.org>
 */
class AbstractProxyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AbstractProxy
     */
    protected $proxy;

    protected function setUp()
    {
        $this->proxy = new ConcreteProxy();
    }

    protected function tearDown()
    {
        $this->proxy = null;
    }

    public function testGetSaveHandlerName()
    {
        $this->assertNull($this->proxy->getSaveHandlerName());
    }

    public function testIsSessionHandlerInterface()
    {
        $this->assertFalse($this->proxy->isSessionHandlerInterface());
        $sh = new ConcreteSessionHandlerInterfaceProxy();
        $this->assertTrue($sh->isSessionHandlerInterface());
    }

    public function testIsWrapper()
    {
        $this->assertFalse($this->proxy->isWrapper());
    }

<<<<<<< HEAD
    public function testIsActivePhp53()
    {
        if (PHP_VERSION_ID >= 50400) {
            $this->markTestSkipped('Test skipped, for PHP 5.3 only.');
        }

        $this->assertFalse($this->proxy->isActive());
    }

=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
<<<<<<< HEAD
    public function testIsActivePhp54()
    {
        if (PHP_VERSION_ID < 50400) {
            $this->markTestSkipped('Test skipped, for PHP 5.4 only.');
        }
=======
    public function testIsActive()
    {
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

        $this->assertFalse($this->proxy->isActive());
        session_start();
        $this->assertTrue($this->proxy->isActive());
    }

<<<<<<< HEAD
    public function testSetActivePhp53()
    {
        if (PHP_VERSION_ID >= 50400) {
            $this->markTestSkipped('Test skipped, for PHP 5.3 only.');
        }

        $this->proxy->setActive(true);
        $this->assertTrue($this->proxy->isActive());
        $this->proxy->setActive(false);
        $this->assertFalse($this->proxy->isActive());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @expectedException \LogicException
     */
    public function testSetActivePhp54()
    {
        if (PHP_VERSION_ID < 50400) {
            $this->markTestSkipped('Test skipped, for PHP 5.4 only.');
        }

        $this->proxy->setActive(true);
    }

=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testName()
    {
        $this->assertEquals(session_name(), $this->proxy->getName());
        $this->proxy->setName('foo');
        $this->assertEquals('foo', $this->proxy->getName());
        $this->assertEquals(session_name(), $this->proxy->getName());
    }

    /**
<<<<<<< HEAD
     * @expectedException \LogicException
     */
    public function testNameExceptionPhp53()
    {
        if (PHP_VERSION_ID >= 50400) {
            $this->markTestSkipped('Test skipped, for PHP 5.3 only.');
        }

        $this->proxy->setActive(true);
        $this->proxy->setName('foo');
    }

    /**
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @expectedException \LogicException
     */
<<<<<<< HEAD
    public function testNameExceptionPhp54()
    {
        if (PHP_VERSION_ID < 50400) {
            $this->markTestSkipped('Test skipped, for PHP 5.4 only.');
        }

=======
    public function testNameException()
    {
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
        session_start();
        $this->proxy->setName('foo');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testId()
    {
        $this->assertEquals(session_id(), $this->proxy->getId());
        $this->proxy->setId('foo');
        $this->assertEquals('foo', $this->proxy->getId());
        $this->assertEquals(session_id(), $this->proxy->getId());
    }

    /**
<<<<<<< HEAD
     * @expectedException \LogicException
     */
    public function testIdExceptionPhp53()
    {
        if (PHP_VERSION_ID >= 50400) {
            $this->markTestSkipped('Test skipped, for PHP 5.3 only.');
        }

        $this->proxy->setActive(true);
        $this->proxy->setId('foo');
    }

    /**
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @expectedException \LogicException
     */
<<<<<<< HEAD
    public function testIdExceptionPhp54()
    {
        if (PHP_VERSION_ID < 50400) {
            $this->markTestSkipped('Test skipped, for PHP 5.4 only.');
        }

=======
    public function testIdException()
    {
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
        session_start();
        $this->proxy->setId('foo');
    }
}
