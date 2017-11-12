<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\HttpCache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface SurrogateInterface
{
    /**
     * Returns surrogate name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Returns a new cache strategy instance.
     *
     * @return ResponseCacheStrategyInterface A ResponseCacheStrategyInterface instance
     */
    public function createCacheStrategy(): ResponseCacheStrategyInterface;

    /**
     * Checks that at least one surrogate has Surrogate capability.
     *
     * @return bool true if one surrogate has Surrogate capability, false otherwise
     */
    public function hasSurrogateCapability(Request $request): bool;

    /**
     * Adds Surrogate-capability to the given Request.
     */
    public function addSurrogateCapability(Request $request): void;

    /**
     * Adds HTTP headers to specify that the Response needs to be parsed for Surrogate.
     *
     * This method only adds an Surrogate HTTP header if the Response has some Surrogate tags.
     */
    public function addSurrogateControl(Response $response): void;

    /**
     * Checks that the Response needs to be parsed for Surrogate tags.
     *
     * @return bool true if the Response needs to be parsed, false otherwise
     */
    public function needsParsing(Response $response): bool;

    /**
     * Renders a Surrogate tag.
     *
     * @param string $uri          A URI
     * @param string $alt          An alternate URI
     * @param bool   $ignoreErrors Whether to ignore errors or not
     * @param string $comment      A comment to add as an esi:include tag
     *
     * @return string
     */
    public function renderIncludeTag(string $uri, string $alt = null, bool $ignoreErrors = true, string $comment = ''): string;

    /**
     * Replaces a Response Surrogate tags with the included resource content.
     *
     * @return Response
     */
    public function process(Request $request, Response $response): Response;

    /**
     * Handles a Surrogate from the cache.
     *
     * @param HttpCache $cache        An HttpCache instance
     * @param string    $uri          The main URI
     * @param string    $alt          An alternative URI
     * @param bool      $ignoreErrors Whether to ignore errors or not
     *
     * @return string
     *
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function handle(HttpCache $cache, string $uri, string $alt, bool $ignoreErrors): string;
}
