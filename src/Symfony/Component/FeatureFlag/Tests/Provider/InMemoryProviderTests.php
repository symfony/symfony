<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\FeatureFlag\Provider\InMemoryProvider;

class InMemoryProviderTests extends TestCase
{
    private InMemoryProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new InMemoryProvider([
            'first' => fn () => true,
            'second' => fn () => 42,
            'exception' => fn () => throw new \LogicException('Should not be called.'),
        ]);
    }

    public function testGet()
    {
        $feature = $this->provider->get('first');
        $this->assertIsCallable($feature);
        $this->assertTrue($feature());

        $feature = $this->provider->get('second');
        $this->assertIsCallable($feature);
        $this->assertSame(42, $feature());
    }

    public function testGetLazy()
    {
        $this->assertIsCallable($this->provider->get('exception'));
    }

    public function testGetNotFound()
    {
        $feature = $this->provider->get('unknown');

        $this->assertNull($feature);
    }

    public function testGetNames()
    {
        $this->assertSame(['first', 'second', 'exception'], $this->provider->getNames());
    }
}
