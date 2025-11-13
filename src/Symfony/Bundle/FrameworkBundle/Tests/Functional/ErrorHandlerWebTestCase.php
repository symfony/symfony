<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

class ErrorHandlerWebTestCase extends AbstractWebTestCase
{
    public function testHtmlErrorResponseOnCliContext()
    {
        $client = self::createClient(['test_case' => 'ErrorHandler', 'root_config' => 'config.yml', 'debug' => false]);
        $client->request('GET', '/_error/500.html');

        self::assertResponseStatusCodeSame(500, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('<!DOCTYPE html>', $client->getResponse()->getContent());
        self::assertStringContainsString('Oops! An Error Occurred', $client->getResponse()->getContent());
    }
}
