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

use Symfony\Component\Asset\Exception\InvalidArgumentException;

/**
 * Manages preload HTTP headers.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PreloadManager implements PreloadManagerInterface
{
    private $resources = array();

    /**
     * {@inheritdoc}
     */
    public function addResource($uri, $as = '', $nopush = false)
    {
        $this->resources[$uri] = array('as' => $as, 'nopush' => $nopush);
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
        foreach ($resources as $key => $options) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('The key must be a path to an asset, "%s" given.', $key);
            }

            if (!isset($options['as']) || !is_string($options['as'])) {
                throw new InvalidArgumentException('The "as" option is mandatory and must be a string.');
            }

            if (!isset($options['nopush']) || !is_bool($options['nopush'])) {
                throw new InvalidArgumentException('The "nopush" option is mandatory and must be a bool.');
            }
        }

        $this->resources = $resources;
    }

    /**
     * {@inheritdoc}
     */
    public function getLinkValue()
    {
        if (!$this->resources) {
            return null;
        }

        $parts = array();
        foreach ($this->resources as $uri => $options) {
            $part = "<$uri>; rel=preload";
            if ('' !== $options['as']) {
                $part .= "; as={$options['as']}";
            }

            if ($options['nopush']) {
                $part .= '; nopush';
            }

            $parts[] = $part;
        }

        return implode(',', $parts);
    }
}
