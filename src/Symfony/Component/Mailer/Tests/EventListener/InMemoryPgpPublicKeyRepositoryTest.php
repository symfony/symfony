<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\EventListener\InMemoryPgpPublicKeyRepository;

class InMemoryPgpPublicKeyRepositoryTest extends TestCase
{
    public function testReturnsTheConfiguredKeyPath()
    {
        $repository = new InMemoryPgpPublicKeyRepository([
            'jane@example.com' => '/path/to/jane.asc',
            'john@example.com' => '/path/to/john.asc',
        ]);

        $this->assertSame('/path/to/jane.asc', $repository->findPublicKeyPathFor('jane@example.com'));
        $this->assertSame('/path/to/john.asc', $repository->findPublicKeyPathFor('john@example.com'));
    }

    public function testReturnsNullForUnknownAddress()
    {
        $repository = new InMemoryPgpPublicKeyRepository(['jane@example.com' => '/path/to/jane.asc']);

        $this->assertNull($repository->findPublicKeyPathFor('unknown@example.com'));
    }

    public function testReturnsNullWhenEmpty()
    {
        $repository = new InMemoryPgpPublicKeyRepository();

        $this->assertNull($repository->findPublicKeyPathFor('jane@example.com'));
    }
}
