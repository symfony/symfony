<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\AbstractRouteHandler;
use Symfony\Component\Routing\Exception\NotAcceptableException;
use Symfony\Component\Routing\CompiledRoute;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesser;
use Symfony\Component\HttpFoundation\AcceptHeader;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class NegotiatedRouteHandler extends AbstractRouteHandler
{
    /**
     * @var array
     */
    private $values;

    /**
     * @var array
     */
    private $headers;

    /**
     * @param HeaderBag $headers
     */
    public function __construct(HeaderBag $headers)
    {
        $this->values = array();
        $this->headers = array();

        if ($headers->has('Accept-Language')) {
            $this->values['_locale'] = $this->buildValues($headers->get('Accept-Language'));
            $this->headers[] = 'Accept-Language';
        }
        if ($headers->has('Accept-Charset')) {
            $this->values['_charset'] = $this->buildValues($headers->get('Accept-Charset'));
            $this->headers[] = 'Accept-Charset';
        }
        if ($headers->has('Accept')) {
            $this->values['_format'] = $this->buildValues($headers->get('Accept'), function ($contentType) {
                return ExtensionGuesser::getInstance()->guess($contentType);
            });
            $this->headers[] = 'Accept';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateBeforeCompilation(Route $route)
    {
        if ($route->getOption('negotiate')) {
            foreach (array_keys($this->values) as $variable) {
                $route->setDefault($variable, null);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkMatcherExceptions(Route $route, CompiledRoute $compiledRoute)
    {
        if ($route->getOption('negotiate')) {
            $variables = array_intersect($compiledRoute->getVariables(), array_keys($this->values));

            foreach ($variables as $variable) {
                $values = $this->values[$variable];

                if (null !== $requirement = $route->getRequirement($variable)) {
                    $values = array_filter($values, function ($value) use ($requirement) {
                        return preg_match('~'.$requirement.'~i', $value);
                    });
                }

                if (count($values)) {
                    $route->setDefault($variable, current($values));
                } else {
                    $message = sprintf('None of the accepted values "%s" match route requirement "%s".', implode(', ', $values), $requirement);

                    throw new NotAcceptableException($variables, $message);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateMatchedParameters(Route $route, array $parameters)
    {
        $parameters['_negotiated_headers'] = $this->headers;;
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
