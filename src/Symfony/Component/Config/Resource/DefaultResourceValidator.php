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
class DefaultResourceValidator implements ResourceValidatorInterface
{
    public function isFresh(ResourceInterface $resource)
    {
        if ($resource instanceof SelfValidatingResourceInterface) {
            return $resource->isFresh();
        }

        return null;
    }
}
