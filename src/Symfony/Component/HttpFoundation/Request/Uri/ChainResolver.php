<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Request\Uri;

use Symfony\Component\HttpFoundation\Request;

/**
 * Request uri resolver that takes a chain of other resolvers and
 * loops through them until one returns a value that !== false.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yosef Deray <yderay@gmail.com>
 */
class ChainResolver implements UriResolverInterface
{
    /**
     * @var UriResolverInterface[]
     */
    private $resolvers = array();

    public function resolveUri(Request $request)
    {
        $requestUri = false;

        foreach ($this->resolvers as $uriResolver) {
            $requestUri = $uriResolver->resolveUri($request);

            if ($requestUri !== false) {
                break;
            }
        }

        return $requestUri;
    }

    /**
     * @param UriResolverInterface $resolver
     *
     * @return $this
     */
    public function add(UriResolverInterface $resolver)
    {
        array_unshift($this->resolvers, $resolver);

        return $this;
    }
}