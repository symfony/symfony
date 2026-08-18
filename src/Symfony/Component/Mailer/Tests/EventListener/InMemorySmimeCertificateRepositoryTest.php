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
use Symfony\Component\Mailer\EventListener\InMemorySmimeCertificateRepository;

class InMemorySmimeCertificateRepositoryTest extends TestCase
{
    public function testReturnsTheConfiguredCertificatePath()
    {
        $repository = new InMemorySmimeCertificateRepository([
            'user@example.com' => '/path/to/user.crt',
        ]);

        $this->assertSame('/path/to/user.crt', $repository->findCertificatePathFor('user@example.com'));
    }

    public function testReturnsNullForUnknownAddress()
    {
        $repository = new InMemorySmimeCertificateRepository();

        $this->assertNull($repository->findCertificatePathFor('unknown@example.com'));
    }
}
