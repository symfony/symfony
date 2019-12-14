<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Google\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Google\Task\JobFactory;
use Symfony\Component\Scheduler\Bridge\Google\Transport\GoogleTransport;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GoogleTransportTest extends TestCase
{
    public function testTransportCanBeCreated(): void
    {
        $transport = new GoogleTransport(Dsn::fromString('google://test@europe-west1/?bearer=test&auth_key=test'), [], new JobFactory());
        static::assertInstanceOf(TransportInterface::class, $transport);

        $transport = new GoogleTransport(Dsn::fromString('gcp://test@europe-west1/?bearer=test&auth_key=test'), [], new JobFactory());
        static::assertInstanceOf(TransportInterface::class, $transport);
    }
}
