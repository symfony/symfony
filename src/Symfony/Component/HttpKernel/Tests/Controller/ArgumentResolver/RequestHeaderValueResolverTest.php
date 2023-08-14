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
use Symfony\Component\HttpKernel\Attribute\MapRequestHeader;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestHeaderValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RequestHeaderValueResolverTest extends TestCase
{
    private ValueResolverInterface $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RequestHeaderValueResolver();
    }

    public function testHeaderParameter()
    {
        $allParameters = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-us,en;q=0.5',
            'Host' => 'localhost',
            'User-Agent' => 'Symfony',
        ];

        foreach ($allParameters  as $parameter => $value) {
            $metadata = new ArgumentMetadata('variableName', 'string', false, false, null, false, [
                MapRequestHeader::class => new MapRequestHeader($parameter),
            ]);

            $arguments = $this->resolver->resolve(Request::create('/'), $metadata);

            self::assertEquals([$value], $arguments);
        }
    }

    public function testHeaderParameterIsMissing()
    {
        $metadata = new ArgumentMetadata('variableName', 'string', false, false, null, false, [
            MapRequestHeader::class => new MapRequestHeader(),
        ]);

        try {
            $this->resolver->resolve(Request::create('/'), $metadata);
        } catch (NotFoundHttpException $exception) {
            self::assertEquals('Missing header parameter "variableName".', $exception->getMessage());
        }
    }
}
