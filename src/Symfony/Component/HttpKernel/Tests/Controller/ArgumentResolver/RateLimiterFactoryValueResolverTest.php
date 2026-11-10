<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RateLimiterFactoryValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\RateLimiter\Attribute\FixedWindowPolicy;
use Symfony\Component\RateLimiter\Attribute\SlidingWindowPolicy;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RateLimiterFactoryValueResolverTest extends TestCase
{
    private RateLimiterFactoryValueResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RateLimiterFactoryValueResolver(new RateLimiterBuilder(new InMemoryStorage()));
    }

    public function testSkipsArgumentsWithoutPolicy()
    {
        $this->assertSame([], $this->resolver->resolve(new Request(), $this->argument('limiter', [])));
    }

    public function testSkipsArgumentsOfAnotherType()
    {
        $argument = new ArgumentMetadata('limiter', 'string', false, false, null, false, [new FixedWindowPolicy(2, '1 hour')], 'Foo::bar');

        $this->assertSame([], $this->resolver->resolve(new Request(), $argument));
    }

    public function testResolvesAFactoryConfiguredByThePolicy()
    {
        $limiter = $this->resolve('limiter')->create('key');

        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertFalse($limiter->consume()->isAccepted());
    }

    public function testEachArgumentGetsItsOwnBucket()
    {
        $this->resolve('limiter')->create('key')->consume(2);

        $this->assertFalse($this->resolve('limiter')->create('key')->consume()->isAccepted(), 'the same argument keeps its bucket across requests');
        $this->assertTrue($this->resolve('otherLimiter')->create('key')->consume()->isAccepted());
    }

    public function testStackedPoliciesAreCombined()
    {
        $factory = $this->resolver->resolve(new Request(), $this->argument('limiter', [new FixedWindowPolicy(5, '1 hour'), new SlidingWindowPolicy(2, '1 hour')]))[0];
        $limiter = $factory->create('key');

        $this->assertInstanceOf(CompoundRateLimiterFactory::class, $factory);
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertFalse($limiter->consume()->isAccepted(), 'the tightest policy rejects');
    }

    public function testStackedPoliciesOfTheSameClassDoNotShareABucket()
    {
        $controller = static function (
            #[FixedWindowPolicy(5, '1 hour')]
            #[FixedWindowPolicy(2, '1 hour')]
            RateLimiterFactoryInterface $limiter,
        ) {
        };
        $argument = (new ArgumentMetadataFactory())->createArgumentMetadata($controller)[0];

        foreach ([true, true, false] as $accepted) {
            $this->assertSame($accepted, $this->resolver->resolve(new Request(), $argument)[0]->create('key')->consume()->isAccepted());
        }
    }

    private function resolve(string $name): RateLimiterFactoryInterface
    {
        return $this->resolver->resolve(new Request(), $this->argument($name, [new FixedWindowPolicy(2, '1 hour')]))[0];
    }

    private function argument(string $name, array $attributes): ArgumentMetadata
    {
        return new ArgumentMetadata($name, RateLimiterFactoryInterface::class, false, false, null, false, $attributes, 'Foo::bar');
    }
}
