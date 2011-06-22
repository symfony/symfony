<?php

namespace Symfony\Component\Security\Core\Util;

/**
 * NullSeedProvider implementation.
 *
 * Never use this for anything but unit testing.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class NullSeedProvider implements SeedProviderInterface
{
    public function loadSeed()
    {
        return array('', new \DateTime());
    }

    public function updateSeed($seed)
    {
    }
}