<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheWarmer;

/**
 * Aggregates several cache warmers into a single one.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class CacheWarmerAggregate implements CacheWarmerInterface
{
    protected $warmers;
    protected $optionalsEnabled;

    /**
     * @since v2.0.0
     */
    public function __construct(array $warmers = array())
    {
        $this->setWarmers($warmers);
        $this->optionalsEnabled = false;
    }

    /**
     * @since v2.0.0
     */
    public function enableOptionalWarmers()
    {
        $this->optionalsEnabled = true;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     *
     * @since v2.0.0
     */
    public function warmUp($cacheDir)
    {
        foreach ($this->warmers as $warmer) {
            if (!$this->optionalsEnabled && $warmer->isOptional()) {
                continue;
            }

            $warmer->warmUp($cacheDir);
        }
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return Boolean always true
     *
     * @since v2.0.0
     */
    public function isOptional()
    {
        return false;
    }

    /**
     * @since v2.0.0
     */
    public function setWarmers(array $warmers)
    {
        $this->warmers = array();
        foreach ($warmers as $warmer) {
            $this->add($warmer);
        }
    }

    /**
     * @since v2.0.0
     */
    public function add(CacheWarmerInterface $warmer)
    {
        $this->warmers[] = $warmer;
    }
}
