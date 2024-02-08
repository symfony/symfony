<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Csp;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles Content-Security-Policy HTTP header for the WebProfiler Bundle.
 *
 * @author Romain Neutron <imprec@gmail.com>
 *
 * @internal
 */
class ContentSecurityPolicyHandler
{
    private NonceGenerator $nonceGenerator;
    private bool $cspDisabled = false;

    public function __construct(NonceGenerator $nonceGenerator)
    {
        $this->nonceGenerator = $nonceGenerator;
    }

    /**
     * Returns an array of nonces to be used in Twig templates and Content-Security-Policy headers.
     *
     * Nonce can be provided by;
     *  - The request - In case HTML content is fetched via AJAX and inserted in DOM, it must use the same nonce as origin
     *  - The response -  A call to getNonces() has already been done previously. Same nonce are returned
     *  - They are otherwise randomly generated
     */
    public function getNonces(Request $request, Response $response): array
    {
        if ($request->headers->has('X-SymfonyProfiler-Script-Nonce') && $request->headers->has('X-SymfonyProfiler-Style-Nonce')) {
            return [
                'csp_script_nonce' => $request->headers->get('X-SymfonyProfiler-Script-Nonce'),
                'csp_style_nonce' => $request->headers->get('X-SymfonyProfiler-Style-Nonce'),
            ];
        }

        if ($response->headers->has('X-SymfonyProfiler-Script-Nonce') && $response->headers->has('X-SymfonyProfiler-Style-Nonce')) {
            return [
                'csp_script_nonce' => $response->headers->get('X-SymfonyProfiler-Script-Nonce'),
                'csp_style_nonce' => $response->headers->get('X-SymfonyProfiler-Style-Nonce'),
            ];
        }

        $nonces = [
            'csp_script_nonce' => $this->generateNonce(),
            'csp_style_nonce' => $this->generateNonce(),
        ];

        $response->headers->set('X-SymfonyProfiler-Script-Nonce', $nonces['csp_script_nonce']);
        $response->headers->set('X-SymfonyProfiler-Style-Nonce', $nonces['csp_style_nonce']);

        return $nonces;
    }

    /**
     * Disables Content-Security-Policy.
     *
     * All related headers will be removed.
     */
    public function disableCsp(): void
    {
        $this->cspDisabled = true;
    }

    /**
     * Cleanup temporary headers and updates Content-Security-Policy headers.
     *
     * @return array Nonces used by the bundle in Content-Security-Policy header
     */
    public function updateResponseHeaders(Request $request, Response $response): array
    {
        if ($this->cspDisabled) {
            $this->removeCspHeaders($response);

            return [];
        }

        $nonces = $this->getNonces($request, $response);
        $this->cleanHeaders($response);
        $this->updateCspHeaders($response, $nonces);

        return $nonces;
    }

    private function cleanHeaders(Response $response): void
    {
        $response->headers->remove('X-SymfonyProfiler-Script-Nonce');
        $response->headers->remove('X-SymfonyProfiler-Style-Nonce');
    }

    private function removeCspHeaders(Response $response): void
    {
        $response->headers->remove('X-Content-Security-Policy');
        $response->headers->remove('Content-Security-Policy');
        $response->headers->remove('Content-Security-Policy-Report-Only');
    }

    /**
     * Updates Content-Security-Policy headers in a response.
     */
    private function updateCspHeaders(Response $response, array $nonces = []): array
    {
        $nonces = array_replace([
            'csp_script_nonce' => $this->generateNonce(),
            'csp_style_nonce' => $this->generateNonce(),
        ], $nonces);

        $ruleIsSet = false;

        $headers = $this->getCspHeaders($response);

        $types = [
          'script-src' => 'csp_script_nonce',
          'script-src-elem' => 'csp_script_nonce',
          'style-src' => 'csp_style_nonce',
          'style-src-elem' => 'csp_style_nonce',
        ];

        foreach ($headers as $header => $directives) {
            foreach ($types as $type => $tokenName) {
                if ($this->authorizesInline($directives, $type)) {
                    continue;
                }
                if (!isset($headers[$header][$type])) {
                    if (null === $fallback = $this->getDirectiveFallback($directives, $type)) {
                        continue;
                    }

                    if (['\'none\''] === $fallback) {
                        // Fallback came from "default-src: 'none'"
                        // 'none' is invalid if it's not the only expression in the source list, so we leave it out
                        $fallback = [];
                    }

                    $headers[$header][$type] = $fallback;
                }
                $ruleIsSet = true;
                if (!\in_array('\'unsafe-inline\'', $headers[$header][$type], true)) {
                    $headers[$header][$type][] = '\'unsafe-inline\'';
                }
                $headers[$header][$type][] = sprintf('\'nonce-%s\'', $nonces[$tokenName]);
            }
        }

        if (!$ruleIsSet) {
            return $nonces;
        }

        foreach ($headers as $header => $directives) {
            $response->headers->set($header, $this->generateCspHeader($directives));
        }

        return $nonces;
    }

    /**
     * Generates a valid Content-Security-Policy nonce.
     */
    private function generateNonce(): string
    {
        return $this->nonceGenerator->generate();
    }

    /**
     * Converts a directive set array into Content-Security-Policy header.
     */
    private function generateCspHeader(array $directives): string
    {
        return array_reduce(array_keys($directives), fn ($res, $name) => ('' !== $res ? $res.'; ' : '').sprintf('%s %s', $name, implode(' ', $directives[$name])), '');
    }

    /**
     * Converts a Content-Security-Policy header value into a directive set array.
     */
    private function parseDirectives(string $header): array
    {
        $directives = [];

        foreach (explode(';', $header) as $directive) {
            $parts = explode(' ', trim($directive));
            if (\count($parts) < 1) {
                continue;
            }
            $name = array_shift($parts);
            $directives[$name] = $parts;
        }

        return $directives;
    }

    /**
     * Detects if the 'unsafe-inline' is prevented for a directive within the directive set.
     */
    private function authorizesInline(array $directivesSet, string $type): bool
    {
        if (isset($directivesSet[$type])) {
            $directives = $directivesSet[$type];
        } elseif (null === $directives = $this->getDirectiveFallback($directivesSet, $type)) {
            return false;
        }

        return \in_array('\'unsafe-inline\'', $directives, true) && !$this->hasHashOrNonce($directives);
    }

    private function hasHashOrNonce(array $directives): bool
    {
        foreach ($directives as $directive) {
            if (!str_ends_with($directive, '\'')) {
                continue;
            }
            if (str_starts_with($directive, '\'nonce-')) {
                return true;
            }
            if (\in_array(substr($directive, 0, 8), ['\'sha256-', '\'sha384-', '\'sha512-'], true)) {
                return true;
            }
        }

        return false;
    }

    private function getDirectiveFallback(array $directiveSet, string $type): ?array
    {
        if (\in_array($type, ['script-src-elem', 'style-src-elem'], true) || !isset($directiveSet['default-src'])) {
            // Let the browser fallback on it's own
            return null;
        }

        return $directiveSet['default-src'];
    }

    /**
     * Retrieves the Content-Security-Policy headers (either X-Content-Security-Policy or Content-Security-Policy) from
     * a response.
     */
    private function getCspHeaders(Response $response): array
    {
        $headers = [];

        if ($response->headers->has('Content-Security-Policy')) {
            $headers['Content-Security-Policy'] = $this->parseDirectives($response->headers->get('Content-Security-Policy'));
        }

        if ($response->headers->has('Content-Security-Policy-Report-Only')) {
            $headers['Content-Security-Policy-Report-Only'] = $this->parseDirectives($response->headers->get('Content-Security-Policy-Report-Only'));
        }

        if ($response->headers->has('X-Content-Security-Policy')) {
            $headers['X-Content-Security-Policy'] = $this->parseDirectives($response->headers->get('X-Content-Security-Policy'));
        }

        return $headers;
    }
}
