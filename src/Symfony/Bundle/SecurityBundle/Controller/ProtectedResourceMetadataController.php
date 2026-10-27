<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the RFC 9728 protected resource metadata document of an "access_token" firewall,
 * which tells a client which authorization servers issue the access tokens the firewall
 * accepts. The route loader declares a route for it at each firewall's well-known path.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9728
 */
final class ProtectedResourceMetadataController
{
    /**
     * @param array<string, array<string, mixed>> $metadata Metadata documents, indexed by firewall name
     */
    public function __construct(
        private readonly array $metadata,
    ) {
    }

    public function __invoke(Request $request, string $firewallName): Response
    {
        if (!isset($this->metadata[$firewallName])) {
            throw new NotFoundHttpException(\sprintf('No protected resource metadata is configured for the "%s" firewall.', $firewallName));
        }

        // the document is public, so clients that run in a browser can read it from any origin
        if ($request->isMethod('OPTIONS')) {
            return new Response(null, Response::HTTP_NO_CONTENT, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET',
                'Access-Control-Allow-Headers' => '*',
            ]);
        }

        $metadata = $this->metadata[$firewallName];

        // the "resource" identifier is REQUIRED by RFC 9728, Section 2; when the firewall
        // does not pin one, the resource is the origin this document is being served from,
        // and a path is resolved against that origin
        $metadata = ['resource' => match (true) {
            !isset($metadata['resource']) => $request->getSchemeAndHttpHost(),
            str_starts_with($metadata['resource'], '/') => $request->getUriForPath($metadata['resource']),
            default => $metadata['resource'],
        }] + $metadata;

        // every other discovery document is served with plain slashes, and RFC 9728,
        // Section 3.3 has the consumer compare the "resource" value character by character
        return (new JsonResponse($metadata, Response::HTTP_OK, ['Access-Control-Allow-Origin' => '*']))->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_SLASHES);
    }
}
