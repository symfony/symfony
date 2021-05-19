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
 */
class SwitchUserTokenProcessorTest extends TestCase
{
    public function testProcessor()
    {
        $originalToken = new UsernamePasswordToken('original_user', 'password', 'provider', ['ROLE_SUPER_ADMIN']);
        $switchUserToken = new SwitchUserToken('user', 'passsword', 'provider', ['ROLE_USER'], $originalToken);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($switchUserToken);

        $processor = new SwitchUserTokenProcessor($tokenStorage);
        $record = ['extra' => []];
        $record = $processor($record);

        $expected = [
            'impersonator_token' => [
                'authenticated' => true,
                'roles' => ['ROLE_SUPER_ADMIN'],
                'username' => 'original_user',
            ],
        ];
        if (method_exists($originalToken, 'getUserIdentifier')) {
            $expected['impersonator_token']['user_identifier'] = 'original_user';
        }

        $this->assertEquals($expected, $record['extra']);
    }
}
