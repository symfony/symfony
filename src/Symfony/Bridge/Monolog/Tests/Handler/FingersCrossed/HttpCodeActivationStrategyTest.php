<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Handler\FingersCrossed;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HttpCodeActivationStrategyTest extends TestCase
{
    /**
     * @expectedException \LogicException
     */
    public function testExclusionsWithoutCode()
    {
        new HttpCodeActivationStrategy(new RequestStack(), array(array('urls' => array())), Logger::WARNING);
    }

    /**
     * @expectedException \LogicException
     */
    public function testExclusionsWithoutUrls()
    {
        new HttpCodeActivationStrategy(new RequestStack(), array(array('code' => 404)), Logger::WARNING);
    }

    /**
     * @dataProvider isActivatedProvider
     */
    public function testIsActivated($url, $record, $expected)
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create($url));

        $strategy = new HttpCodeActivationStrategy(
            $requestStack,
            array(
                array('code' => 403, 'urls' => array()),
                array('code' => 404, 'urls' => array()),
                array('code' => 405, 'urls' => array()),
                array('code' => 400, 'urls' => array('^/400/a', '^/400/b')),
            ),
            Logger::WARNING
        );

        $this->assertEquals($expected, $strategy->isHandlerActivated($record));
    }

    public function isActivatedProvider()
    {
        return array(
            array('/test',  array('level' => Logger::ERROR), true),
            array('/400',   array('level' => Logger::ERROR, 'context' => $this->getContextException(400)), true),
            array('/400/a', array('level' => Logger::ERROR, 'context' => $this->getContextException(400)), false),
            array('/400/b', array('level' => Logger::ERROR, 'context' => $this->getContextException(400)), false),
            array('/400/c', array('level' => Logger::ERROR, 'context' => $this->getContextException(400)), true),
            array('/401',   array('level' => Logger::ERROR, 'context' => $this->getContextException(401)), true),
            array('/403',   array('level' => Logger::ERROR, 'context' => $this->getContextException(403)), false),
            array('/404',   array('level' => Logger::ERROR, 'context' => $this->getContextException(404)), false),
            array('/405',   array('level' => Logger::ERROR, 'context' => $this->getContextException(405)), false),
            array('/500',   array('level' => Logger::ERROR, 'context' => $this->getContextException(500)), true),
        );
    }

    protected function getContextException($code)
    {
        return array('exception' => new HttpException($code));
    }
}
