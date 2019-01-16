<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheClearer;

/**
 * ChainCacheClearer.
 *
 * @author Dustin Dobervich <ddobervich@gmail.com>
 *
 * @final since version 3.4
 */
class ChainCacheClearer implements CacheClearerInterface
{
    protected $clearers;

    /**
     * Constructs a new instance of ChainCacheClearer.
     *
     * @param array $clearers The initial clearers
     */
    public function __construct($clearers = [])
    {
        $this->clearers = $clearers;
    }

    /**
     * {@inheritdoc}
     */
    public function clear($cacheDir)
    {
        foreach ($this->clearers as $clearer) {
            $clearer->clear($cacheDir);
        }
    }

    /**
     * Adds a cache clearer to the aggregate.
     *
     * @deprecated since version 3.4, to be removed in 4.0, inject the list of clearers as a constructor argument instead.
     */
    public function add(CacheClearerInterface $clearer)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 3.4 and will be removed in 4.0, inject the list of clearers as a constructor argument instead.', __METHOD__), E_USER_DEPRECATED);

        $this->clearers[] = $clearer;
    }
}
