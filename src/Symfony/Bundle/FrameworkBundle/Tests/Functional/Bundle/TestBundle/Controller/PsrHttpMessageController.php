<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class PsrHttpMessageController
{
    public function __construct(
        private readonly ResponseFactoryInterface&StreamFactoryInterface $factory,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $responsePayload = json_encode([
            'message' => sprintf('Hello %s!', $request->getQueryParams()['name'] ?? 'World'),
        ], \JSON_THROW_ON_ERROR);

        return $this->factory->createResponse()
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($responsePayload))
        ;
    }
}
