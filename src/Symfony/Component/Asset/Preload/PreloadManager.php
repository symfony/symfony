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
    public function clear()
    {
        $this->resources = array();
    }

    /**
     * {@inheritdoc}
     */
    public function buildLinkValue()
    {
        if (!$this->resources) {
            return null;
        }

        $parts = array();
        foreach ($this->resources as $uri => $options) {
            $as = '' === $options['as'] ? '' : sprintf('; as=%s', $options['as']);
            $nopush = $options['nopush'] ? '; nopush' : '';

            $parts[] = sprintf('<%s>; rel=preload%s%s', $uri, $as, $nopush);
        }

        return implode(',', $parts);
    }
}
