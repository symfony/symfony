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
 * @final since version 3.4
 */
class CacheWarmerAggregate implements CacheWarmerInterface
{
    protected $warmers = [];
    protected $optionalsEnabled = false;
    private $triggerDeprecation = false;

    public function __construct($warmers = [])
    {
        foreach ($warmers as $warmer) {
            $this->add($warmer);
        }
        $this->triggerDeprecation = true;
    }

    public function enableOptionalWarmers()
    {
        $this->optionalsEnabled = true;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
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
     * @return bool always false
     */
    public function isOptional()
    {
        return false;
    }

    /**
     * @deprecated since version 3.4, to be removed in 4.0, inject the list of clearers as a constructor argument instead.
     */
    public function setWarmers(array $warmers)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 3.4 and will be removed in 4.0, inject the list of clearers as a constructor argument instead.', __METHOD__), \E_USER_DEPRECATED);

        $this->warmers = [];
        foreach ($warmers as $warmer) {
            $this->add($warmer);
        }
    }

    /**
     * @deprecated since version 3.4, to be removed in 4.0, inject the list of clearers as a constructor argument instead.
     */
    public function add(CacheWarmerInterface $warmer)
    {
        if ($this->triggerDeprecation) {
            @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 3.4 and will be removed in 4.0, inject the list of clearers as a constructor argument instead.', __METHOD__), \E_USER_DEPRECATED);
        }

        $this->warmers[] = $warmer;
    }
}
