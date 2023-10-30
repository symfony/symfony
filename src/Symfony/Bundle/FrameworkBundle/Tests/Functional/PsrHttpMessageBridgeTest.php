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

use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class PsrHttpMessageBridgeTest extends AbstractWebTestCase
{
    public function testBridgeIntegration()
    {
        if ((new \ReflectionClass(PsrHttpFactory::class))->getConstructor()->getNumberOfRequiredParameters() > 0) {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('PSR HTTP Message support cannot be enabled for version 2 or earlier. Please update symfony/psr-http-message-bridge to 6.4 or wire all services manually.');
        }

        $client = $this->createClient(['test_case' => 'PsrHttpMessageBridge', 'root_config' => 'config.yml', 'debug' => true]);
        $client->request('GET', '/psr_http?name=Symfony');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonStringEqualsJsonString('{"message":"Hello Symfony!"}', $client->getResponse()->getContent());
    }
}
