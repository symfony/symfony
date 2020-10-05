<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Processor;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Processor\SwitchUserTokenProcessor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Tests the SwitchUserTokenProcessor.
 *
 * @author Igor Timoshenko <igor.timoshenko@i.ua>
 */
class SwitchUserTokenProcessorTest extends TestCase
{
    public function testProcessor()
    {
        $originalToken = new UsernamePasswordToken('original_user', 'password', 'provider', ['ROLE_SUPER_ADMIN']);
        $switchUserToken = new SwitchUserToken('user', 'passsword', 'provider', ['ROLE_USER'], $originalToken);
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage->method('getToken')->willReturn($switchUserToken);

        $processor = new SwitchUserTokenProcessor($tokenStorage);
        $record = ['extra' => []];
        $record = $processor($record);

        $expected = [
            'impersonator_token' => [
                'username' => 'original_user',
                'authenticated' => true,
                'roles' => ['ROLE_SUPER_ADMIN'],
            ],
        ];
        $this->assertSame($expected, $record['extra']);
    }
}
