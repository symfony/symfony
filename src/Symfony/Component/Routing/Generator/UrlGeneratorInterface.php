<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Generator;

use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * UrlGeneratorInterface is the interface that all URL generator classes must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @api
 */
interface UrlGeneratorInterface extends RequestContextAwareInterface
{
    /**
     * These constants define the different types of resource references that are declared
     * in RFC 3986: http://tools.ietf.org/html/rfc3986
     * We are using the term "URL" instead of "URI" as this is more common in web applications
     * and we do not need to distinguish them as the difference is mostly semantical and
     * less technical. Generating URIs, i.e. representation-independent resource identifiers,
     * is still possible.
     */
    const ABSOLUTE_URL = 'url';
    const ABSOLUTE_PATH = 'path';
    const RELATIVE_PATH = 'relative';
    const NETWORK_PATH = 'network';

    /**
     * Generates a URL from the given parameters.
     *
     * If the generator is not able to generate the url, it must throw the RouteNotFoundException
     * as documented below.
     *
     * @param string $name          The name of the route
     * @param mixed  $parameters    An array of parameters
     * @param string $referenceType The type of reference to be generated (see defined constants)
     *
     * @return string The generated URL
     *
     * @throws RouteNotFoundException if route doesn't exist
     *
     * @api
     */
    public function generate($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH);
}
