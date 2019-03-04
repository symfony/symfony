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
use Symfony\Bridge\Monolog\Handler\FingersCrossed\NotFoundActivationStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NotFoundActivationStrategyTest extends TestCase
{
    /**
     * @dataProvider isActivatedProvider
     */
    public function testIsActivated($url, $record, $expected)
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create($url));

        $strategy = new NotFoundActivationStrategy($requestStack, ['^/foo', 'bar'], Logger::WARNING);

        $this->assertEquals($expected, $strategy->isHandlerActivated($record));
    }

    public function isActivatedProvider()
    {
        return [
            ['/test',      ['level' => Logger::DEBUG], false],
            ['/foo',       ['level' => Logger::DEBUG, 'context' => $this->getContextException(404)], false],
            ['/baz/bar',   ['level' => Logger::ERROR, 'context' => $this->getContextException(404)], false],
            ['/foo',       ['level' => Logger::ERROR, 'context' => $this->getContextException(404)], false],
            ['/foo',       ['level' => Logger::ERROR, 'context' => $this->getContextException(500)], true],

            ['/test',      ['level' => Logger::ERROR], true],
            ['/baz',       ['level' => Logger::ERROR, 'context' => $this->getContextException(404)], true],
            ['/baz',       ['level' => Logger::ERROR, 'context' => $this->getContextException(500)], true],
        ];
    }

    protected function getContextException($code)
    {
        return ['exception' => new HttpException($code)];
    }
}
