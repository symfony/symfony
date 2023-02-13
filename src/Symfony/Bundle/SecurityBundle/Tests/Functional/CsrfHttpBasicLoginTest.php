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

class CsrfHttpBasicLoginTest extends AbstractWebTestCase
{
    public function testFormSubmitAfterLogin()
    {
        $client = $this->createClient(
            [
                'test_case' => 'CsrfHttpBasicLogin',
                'root_config' => 'config.yml',
            ],
            [
                'PHP_AUTH_USER' => 'johannes',
                'PHP_AUTH_PW' => 'test',
            ]
        );

        $client->request('GET', '/');
        $client->submitForm('submit');

        $this->assertResponseIsSuccessful();
    }
}
