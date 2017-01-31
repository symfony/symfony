<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Preload;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preload manager for the HttpFoundation component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class HttpFoundationPreloadManager implements PreloadManagerInterface
{
    private $resources = array();

    /**
     * {@inheritdoc}
     */
    public function addResource($uri, $as)
    {
        $this->resources[$uri] = $as;
    }

    /**
     * {@inheritdoc}
     */
    public function getResources()
    {
        return $this->resources;
    }

    /**
     * {@inheritdoc}
     */
    public function setResources(array $resources)
    {
        $this->resources = $resources;
    }

    /**
     * Sets preload Link HTTP header.
     *
     * @param Response $response
     */
    public function setLinkHeader(Response $response)
    {
        if (!$this->resources) {
            return;
        }

        $parts = array();
        foreach ($this->resources as $uri => $as) {
            $parts[] = "<$uri>; rel=preload; as=$as";
        }

        $response->headers->set('Link', implode(',', $parts));
    }
}
