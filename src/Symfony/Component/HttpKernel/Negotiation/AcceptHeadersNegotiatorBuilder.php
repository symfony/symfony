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

use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesser;
use Symfony\Component\Routing\Matcher\Negotiator;

/**
 * AcceptHeadersNegotiatorBuilder.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class AcceptHeadersNegotiatorBuilder implements MatcherNegotiatorBuilderInterface
{
    private $varyingHeaders;
    private $parametersValues;

    /**
     * @param HeaderBag $headers
     */
    public function __construct(HeaderBag $headers)
    {
        $this->values = array();
        $this->headers = array();

        if ($headers->has('Accept-Language')) {
            $this->parametersValues['_locale'] = $this->buildValues($headers->get('Accept-Language'));
            $this->varyingHeaders['_locale'] = 'Accept-Language';
        }
        if ($headers->has('Accept-Charset')) {
            $this->parametersValues['_charset'] = $this->buildValues($headers->get('Accept-Charset'));
            $this->varyingHeaders['_charset'] = 'Accept-Charset';
        }
        if ($headers->has('Accept')) {
            $this->parametersValues['_format'] = $this->buildValues($headers->get('Accept'), function ($contentType) {
                return ExtensionGuesser::getInstance()->guess($contentType);
            });
            $this->varyingHeaders['_format'] = 'Accept';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildNegotiator(Negotiator $negotiator)
    {
        foreach ($this->parametersValues as $parameter => $values) {
            $negotiator->setValues($parameter, $values);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVaryingHeaders(Negotiator $negotiator)
    {
        $varyingHeaders = array();
        foreach ($negotiator->getNegotiatedParameters() as $parameter) {
            $varyingHeaders[] = $this->varyingHeaders[$parameter];
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
