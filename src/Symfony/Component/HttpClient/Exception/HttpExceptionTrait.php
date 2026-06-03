<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Exception;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait HttpExceptionTrait
{
    public function __construct(
        private ResponseInterface $response,
    ) {
        $code = $response->getInfo('http_code');
        $url = $response->getInfo('url');
        $message = \sprintf('HTTP %d returned for "%s".', $code, $url);

        $httpCodeFound = false;
        $isJson = false;
        foreach (array_reverse($response->getInfo('response_headers')) as $h) {
            if (str_starts_with($h, 'HTTP/')) {
                if ($httpCodeFound) {
                    break;
                }

                $message = \sprintf('%s returned for "%s".', $h, $url);
                $httpCodeFound = true;
            }

            if (0 === stripos($h, 'content-type:')) {
                if (preg_match('/\bjson\b/i', $h)) {
                    $isJson = true;
                }

                if ($httpCodeFound) {
                    break;
                }
            }
        }

        // Try to guess a better error message using common API error formats
        // The MIME type isn't explicitly checked because some formats inherit from others
        // Ex: JSON:API follows RFC 7807 semantics, Hydra can be used in any JSON-LD-compatible format
        if ($isJson && $body = json_decode($response->getContent(false), true)) {
            if (isset($body['hydra:title']) || isset($body['hydra:description'])) {
                // see http://www.hydra-cg.com/spec/latest/core/#description-of-http-status-codes-and-errors
                $separator = isset($body['hydra:title'], $body['hydra:description']) ? "\n\n" : '';
                $message = ($body['hydra:title'] ?? '').$separator.($body['hydra:description'] ?? '');
            } elseif ((isset($body['title']) || isset($body['detail']))
                && (\is_scalar($body['title'] ?? '') && \is_scalar($body['detail'] ?? ''))) {
                // see RFC 7807 and https://jsonapi.org/format/#error-objects
                $separator = isset($body['title'], $body['detail']) ? "\n\n" : '';
                $message = ($body['title'] ?? '').$separator.($body['detail'] ?? '');
            }
        }

        parent::__construct($message, $code);
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
