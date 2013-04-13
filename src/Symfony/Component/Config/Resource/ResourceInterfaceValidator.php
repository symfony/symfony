<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

use Symfony\Component\Config\ConfigCacheInterface;

/**
 * This validator supports resources that implement ResourceInterface.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ResourceInterfaceValidator implements ResourceValidatorInterface
{
    public function isFresh($resource, ConfigCacheInterface $cache)
    {
        return $resource->isFresh($cache->getCreationTime());
    }

    public function supports($resource)
    {
        return ($resource instanceof ResourceInterface);
    }

}
