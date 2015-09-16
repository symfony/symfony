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

use Symfony\Component\Config\ResourceCheckerInterface;

/**
 * Resource checker for instances of
 * SelfCheckingResourceInterface. As these resources can
 * perform the check themselves, we can support them in a generic
 * way.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class DefaultResourceChecker implements ResourceCheckerInterface
{
    public function supports(ResourceInterface $metadata)
    {
        return $metadata instanceof SelfCheckingResourceInterface;
    }

    public function isFresh(ResourceInterface $metadata, $timestamp)
    {
        /** @var SelfCheckingResourceInterface $metadata */
        return $metadata->isFresh($timestamp);
    }
}
