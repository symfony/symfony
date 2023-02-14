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

use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Handler\FingersCrossed\NotFoundActivationStrategy;
use Symfony\Bridge\Monolog\Tests\RecordFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NotFoundActivationStrategyTest extends TestCase
{
    /**
     * @dataProvider isActivatedProvider
     */
    public function testIsActivated(string $url, array|LogRecord $record, bool $expected)
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create($url));

        $strategy = new NotFoundActivationStrategy($requestStack, ['^/foo', 'bar'], new ErrorLevelActivationStrategy(Logger::WARNING));

        self::assertEquals($expected, $strategy->isHandlerActivated($record));
    }

    public static function isActivatedProvider(): array
    {
        return [
            ['/test',      RecordFactory::create(Logger::DEBUG), false],
            ['/foo',       RecordFactory::create(Logger::DEBUG, context: self::getContextException(404)), false],
            ['/baz/bar',   RecordFactory::create(Logger::ERROR, context: self::getContextException(404)), false],
            ['/foo',       RecordFactory::create(Logger::ERROR, context: self::getContextException(404)), false],
            ['/foo',       RecordFactory::create(Logger::ERROR, context: self::getContextException(500)), true],

            ['/test',      RecordFactory::create(Logger::ERROR), true],
            ['/baz',       RecordFactory::create(Logger::ERROR, context: self::getContextException(404)), true],
            ['/baz',       RecordFactory::create(Logger::ERROR, context: self::getContextException(500)), true],
        ];
    }

    protected static function getContextException(int $code): array
    {
        return ['exception' => new HttpException($code)];
    }
}
