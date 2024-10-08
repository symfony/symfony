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
use Symfony\Component\FeatureFlag\Provider\ChainProvider;
use Symfony\Component\FeatureFlag\Provider\InMemoryProvider;

class ChainProviderTests extends TestCase
{
    private ChainProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ChainProvider([
            new InMemoryProvider([
                'first' => fn () => true,
            ]),
            new InMemoryProvider([
                'second' => fn () => true,
            ]),
            new InMemoryProvider([
                'exception' => fn () => throw new \LogicException('Should not be called.'),
            ]),
        ]);
    }

    public function testHas()
    {
        $this->assertTrue($this->provider->has('first'));
        $this->assertTrue($this->provider->has('second'));
        $this->assertTrue($this->provider->has('exception'));
        $this->assertFalse($this->provider->has('unknown'));
    }

    public function testGet()
    {
        $feature = $this->provider->get('first');

        $this->assertIsCallable($feature);
        $this->assertTrue($feature());
    }

    public function testGetFallback()
    {
        $feature = $this->provider->get('second');

        $this->assertIsCallable($feature);
        $this->assertTrue($feature());
    }

    public function testGetLazy()
    {
        $this->assertIsCallable($this->provider->get('exception'));
    }

    public function testGetNotFound()
    {
        $feature = $this->provider->get('unknown');

        $this->assertIsCallable($feature);
        $this->assertFalse($feature());
    }

    public function testGetNames()
    {
        $this->assertSame(['first', 'second', 'exception'], $this->provider->getNames());
    }
}
