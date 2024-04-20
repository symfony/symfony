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

use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV6;

class UidTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::deleteTmpDir();
    }

    public function testArgumentValueResolverDisabled()
    {
        $client = $this->createClient(['test_case' => 'Uid', 'root_config' => 'config_disabled.yml']);
        $client->catchExceptions(false);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller\UidController::anyFormat(): Argument #1 ($userId) must be of type Symfony\Component\Uid\UuidV1, string given');

        $client->request('GET', '/1/uuid-v1/'.new UuidV1());
    }

    public function testArgumentValueResolverEnabled()
    {
        $client = $this->createClient(['test_case' => 'Uid', 'root_config' => 'config_enabled.yml']);

        // Any format
        $client->request('GET', '/1/uuid-v1/'.$uuidV1 = new UuidV1());
        $this->assertSame((string) $uuidV1, $client->getResponse()->getContent());
        $client->request('GET', '/1/uuid-v1/'.$uuidV1->toBase58());
        $this->assertSame((string) $uuidV1, $client->getResponse()->getContent());
        $client->request('GET', '/1/uuid-v1/'.$uuidV1->toRfc4122());
        $this->assertSame((string) $uuidV1, $client->getResponse()->getContent());
        // Bad version
        $client->request('GET', '/1/uuid-v1/'.$uuidV4 = new UuidV4());
        $this->assertSame(404, $client->getResponse()->getStatusCode());

        // Only base58 format
        $client->request('GET', '/2/ulid/'.($ulid = new Ulid())->toBase58());
        $this->assertSame((string) $ulid, $client->getResponse()->getContent());
        $client->request('GET', '/2/ulid/'.$ulid);
        $this->assertSame(404, $client->getResponse()->getStatusCode());
        $client->request('GET', '/2/ulid/'.$ulid->toRfc4122());
        $this->assertSame(404, $client->getResponse()->getStatusCode());

        // Only base32 format
        $client->request('GET', '/3/uuid-v1/'.$uuidV1->toBase32());
        $this->assertSame((string) $uuidV1, $client->getResponse()->getContent());
        $client->request('GET', '/3/uuid-v1/'.$uuidV1);
        $this->assertSame(404, $client->getResponse()->getStatusCode());
        $client->request('GET', '/3/uuid-v1/'.$uuidV1->toBase58());
        $this->assertSame(404, $client->getResponse()->getStatusCode());
        // Bad version
        $client->request('GET', '/3/uuid-v1/'.(new UuidV6())->toBase32());
        $this->assertSame(404, $client->getResponse()->getStatusCode());

        // Any format for both
        $client->request('GET', '/4/uuid-v1/'.$uuidV1.'/custom-uid/'.$ulid->toRfc4122());
        $this->assertSame($uuidV1."\n".$ulid, $client->getResponse()->getContent());
        $client->request('GET', '/4/uuid-v1/'.$uuidV1->toBase58().'/custom-uid/'.$ulid->toBase58());
        $this->assertSame($uuidV1."\n".$ulid, $client->getResponse()->getContent());
        // Bad version
        $client->request('GET', '/4/uuid-v1/'.$uuidV4.'/custom-uid/'.$ulid);
        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }
}
