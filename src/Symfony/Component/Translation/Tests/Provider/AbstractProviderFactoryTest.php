<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Exception\IncompleteDsnException;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\NullProvider;
use Symfony\Component\Translation\Provider\ProviderInterface;

class AbstractProviderFactoryTest extends TestCase
{
    public function testMissingPasswordDoesNotLeakCredentials()
    {
        $factory = new class extends AbstractProviderFactory {
            public function create(Dsn $dsn): ProviderInterface
            {
                $this->getPassword($dsn);

                return new NullProvider();
            }

            protected function getSupportedSchemes(): array
            {
                return ['foo'];
            }
        };

        try {
            $factory->create(new Dsn('foo://s3cr3t@default?token=s3cr3t'));
            $this->fail('An exception should have been thrown.');
        } catch (IncompleteDsnException $e) {
            $this->assertSame('Invalid "foo://default" provider DSN: Password is not set.', $e->getMessage());
            $this->assertStringNotContainsString('s3cr3t', $e->getMessage());
        }
    }
}
