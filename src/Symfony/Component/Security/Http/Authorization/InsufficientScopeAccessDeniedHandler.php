<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authorization;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\AccessToken\SenderConstraintInterface;

/**
 * Answers the RFC 6750 §3.1 "insufficient_scope" challenge when access was denied for a missing OAuth2 scope.
 *
 * Access denials that no OAuth2 scope took part in are handed to the handler the application configured,
 * or left to the regular handling when it configured none, so that a firewall mixing scopes with roles
 * keeps answering the latter the way the application asked for.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6750#section-3.1
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
class InsufficientScopeAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    /**
     * The description RFC 6750 §3.1 gives for the "insufficient_scope" error code.
     */
    private const ERROR_DESCRIPTION = 'The request requires higher privileges than provided by the access token.';

    /**
     * @param SenderConstraintInterface|null $senderConstraint What the firewall binds its access tokens to, which
     *                                                         names the scheme of this challenge as it names the
     *                                                         one of a 401 (RFC 9449, Section 7.1)
     */
    public function __construct(
        private ?string $realm = null,
        private ?AccessDeniedHandlerInterface $inner = null,
        private ?string $resourceMetadataUri = null,
        private ?SenderConstraintInterface $senderConstraint = null,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $scopes = [];
        foreach ($accessDeniedException->getAttributes() as $attribute) {
            if (\is_string($attribute) && null !== $requiredScopes = OAuth2ScopeVoter::getRequiredScopes($attribute)) {
                $scopes = array_merge($scopes, $requiredScopes);
            }
        }

        if (!$scopes) {
            return $this->inner?->handle($request, $accessDeniedException);
        }

        return new Response(
            null,
            Response::HTTP_FORBIDDEN,
            ['WWW-Authenticate' => $this->getAuthenticateHeader($request, $scopes)]
        );
    }

    /**
     * @param string[] $scopes
     *
     * RFC 9728 §5.1 does not restrict "resource_metadata" to a 401, and a client denied for a
     * missing scope is the one that most needs to find the authorization server again.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc6750#section-3
     * @see https://datatracker.ietf.org/doc/html/rfc9728#section-5.1
     */
    private function getAuthenticateHeader(Request $request, array $scopes): string
    {
        // a denial is no failure of the proof of possession, so the constraint answers it with its scheme
        // and with nothing else; "insufficient_scope" describes the token, which is a valid one
        [$scheme] = $this->senderConstraint?->getChallenge(null) ?? ['Bearer'];

        $data = [
            'realm' => $this->realm,
            'error' => 'insufficient_scope',
            'error_description' => self::ERROR_DESCRIPTION,
            'scope' => implode(' ', array_unique($scopes)),
            'resource_metadata' => match (true) {
                null === $this->resourceMetadataUri => null,
                str_starts_with($this->resourceMetadataUri, '/') => $request->getUriForPath($this->resourceMetadataUri),
                default => $this->resourceMetadataUri,
            },
        ];
        $values = [];
        foreach ($data as $k => $v) {
            if (null === $v || '' === $v) {
                continue;
            }
            $values[] = \sprintf('%s="%s"', $k, $v);
        }

        return \sprintf('%s %s', $scheme, implode(',', $values));
    }
}
