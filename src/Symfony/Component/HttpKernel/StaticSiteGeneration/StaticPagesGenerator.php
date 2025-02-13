<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\StaticSiteGeneration;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\RuntimeException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * @author Thomas Bibaut <bibaut.t@gmail.com>
 */
final readonly class StaticPagesGenerator
{
    public function __construct(
        private HttpKernelInterface $kernel,
    ) {
    }

    /**
     * Generate page content for URI.
     *
     * @return array{content: string, format: ?string}
     *
     * @throws RuntimeException
     */
    public function generate(string $uri): array
    {
        $request = Request::create($uri);
        try {
            $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST);

            if ($this->kernel instanceof TerminableInterface) {
                $this->kernel->terminate($request, $response);
            }
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('Cannot generate page for URI "%s".', $uri), $e->getCode(), $e);
        }

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('Expected URI "%s" to return status code 200, got %d.', $uri, $response->getStatusCode()));
        }

        return [
            'content' => $response->getContent(),
            'format' => $request->getFormat($response->headers->get('Content-Type')),
        ];
    }
}
