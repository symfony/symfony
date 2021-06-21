<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\SessionHandlerFactory;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;

/**
 * Test class for SessionHandlerFactory.
 *
 * @author Simon <simon.chrzanowski@quentic.com>
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SessionHandlerFactoryTest extends TestCase
{
    /**
     * @dataProvider provideConnectionDSN
     */
    public function testCreateHandler(string $connectionDSN, string $expectedPath, string $expectedHandlerType)
    {
        $handler = SessionHandlerFactory::createHandler($connectionDSN);

        $this->assertInstanceOf($expectedHandlerType, $handler);
        $this->assertEquals($expectedPath, ini_get('session.save_path'));
    }

    public function provideConnectionDSN(): array
    {
        $base = sys_get_temp_dir();

        return [
            'native file handler using save_path from php.ini' => ['connectionDSN' => 'file://', 'expectedPath' => ini_get('session.save_path'), 'expectedHandlerType' => StrictSessionHandler::class],
            'native file handler using provided save_path' => ['connectionDSN' => 'file://'.$base.'/session/storage', 'expectedPath' => $base.'/session/storage', 'expectedHandlerType' => StrictSessionHandler::class],
        ];
    }
}
