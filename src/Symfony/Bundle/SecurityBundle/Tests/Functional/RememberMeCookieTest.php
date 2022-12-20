<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class RememberMeCookieTest extends AbstractWebTestCase
{
    /** @dataProvider getSessionRememberMeSecureCookieFlagAutoHttpsMap */
    public function testSessionRememberMeSecureCookieFlagAuto($https, $expectedSecureFlag)
    {
        $client = self::createClient(['test_case' => 'RememberMeCookie', 'root_config' => 'config.yml']);

        $client->request('POST', '/login', [
            '_username' => 'test',
            '_password' => 'test',
        ], [], [
             'HTTPS' => (int) $https,
        ]);

        $cookies = $client->getResponse()->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        self::assertSame($expectedSecureFlag, $cookies['']['/']['REMEMBERME']->isSecure());
    }

    /**
     * @dataProvider getSessionRememberMeSecureCookieFlagAutoHttpsMap
     * @group legacy
     */
    public function testLegacySessionRememberMeSecureCookieFlagAuto($https, $expectedSecureFlag)
    {
        $client = self::createClient(['test_case' => 'RememberMeCookie', 'root_config' => 'legacy_config.yml']);

        $client->request('POST', '/login', [
            '_username' => 'test',
            '_password' => 'test',
        ], [], [
            'HTTPS' => (int) $https,
        ]);

        $cookies = $client->getResponse()->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        self::assertSame($expectedSecureFlag, $cookies['']['/']['REMEMBERME']->isSecure());
    }

    public function getSessionRememberMeSecureCookieFlagAutoHttpsMap()
    {
        return [
            [true, true],
            [false, false],
        ];
    }
}
