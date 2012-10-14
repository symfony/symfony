<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Negotiation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesser;
use Symfony\Component\Routing\Matcher\NegotiatorInterface;

/**
 * AcceptHeadersNegotiatorBuilder.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class AcceptHeadersNegotiatorBuilder implements MatcherNegotiatorBuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildNegotiator(NegotiatorInterface $negotiator, Request $request)
    {
        if ($request->headers->has('Accept-Language')) {
            $negotiator->setValues('_locale', $this->buildValues($request->headers->get('Accept-Language')));
        }
        if ($request->headers->has('Accept-Charset')) {
            $negotiator->setValues('_charset', $this->buildValues($request->headers->get('Accept-Charset')));
        }
        if ($request->headers->has('Accept')) {
            $negotiator->setValues('_format',  $this->buildValues($request->headers->get('Accept'), function ($contentType) {
                return ExtensionGuesser::getInstance()->guess($contentType);
            }));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVaryingHeaders(NegotiatorInterface $negotiator, Request $request)
    {
        $varyingHeaders = array();
        if ($request->headers->has('Accept-Language')) {
            $varyingHeaders[] = 'Accept-Language';
        }
        if ($request->headers->has('Accept-Charset')) {
            $varyingHeaders[] = 'Accept-Charset';
        }
        if ($request->headers->has('Accept')) {
            $varyingHeaders[] = 'Accept';
        }

        return $varyingHeaders;
    }

    /**
     * @param string       $header
     * @param Closure|null $mapper
     *
     * @return array
     */
    private function buildValues($header, \Closure $mapper = null)
    {
        $qualities = AcceptHeader::split($header);
        asort($qualities);
        $values = array_keys($qualities);

        return $mapper ? array_map($mapper, $values) : $values;
    }
}
