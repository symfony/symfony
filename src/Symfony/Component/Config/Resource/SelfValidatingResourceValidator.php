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

/**
 * This is a default ResourceValidator that supports those resources implementing
 * SelfValidatingResourceInterface.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class SelfValidatingResourceValidator implements ResourceValidatorInterface
{
    public function isFresh(ResourceInterface $resource)
    {
        return $resource->isFresh();
    }

    public function supports(ResourceInterface $resource) {
        return ($resource instanceof SelfValidatingResourceInterface);
    }

}
