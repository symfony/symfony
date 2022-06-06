<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\HttpCache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpCache\HttpCache as BaseHttpCache;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Component\HttpKernel\HttpCache\SurrogateInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Manages HTTP cache objects in a Container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpCache extends BaseHttpCache
{
    protected $cacheDir;
    protected $kernel;

    private $store;
    private $surrogate;
    private $options;

    /**
     * @param string|StoreInterface $cache The cache directory (default used if null) or the storage instance
     */
    public function __construct(KernelInterface $kernel, $cache = null, SurrogateInterface $surrogate = null, array $options = null)
    {
        $this->kernel = $kernel;
        $this->surrogate = $surrogate;
        $this->options = $options ?? [];

        if ($cache instanceof StoreInterface) {
            $this->store = $cache;
        } elseif (null !== $cache && !\is_string($cache)) {
            throw new \TypeError(sprintf('Argument 2 passed to "%s()" must be a string or a SurrogateInterface, "%s" given.', __METHOD__, get_debug_type($cache)));
        } else {
            $this->cacheDir = $cache;
        }

        if (null === $options && $kernel->isDebug()) {
            $this->options = ['debug' => true];
        }

        if ($this->options['debug'] ?? false) {
            $this->options += ['stale_if_error' => 0];
        }

        parent::__construct($kernel, $this->createStore(), $this->createSurrogate(), array_merge($this->options, $this->getOptions()));
    }

    /**
     * {@inheritdoc}
     */
    protected function forward(Request $request, bool $catch = false, Response $entry = null)
    {
        $this->getKernel()->boot();
        $this->getKernel()->getContainer()->set('cache', $this);

        return parent::forward($request, $catch, $entry);
    }

    /**
     * Returns an array of options to customize the Cache configuration.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }

    /**
     * @return SurrogateInterface
     */
    protected function createSurrogate()
    {
        return $this->surrogate ?? new Esi();
    }

    /**
     * @return StoreInterface
     */
    protected function createStore()
    {
        return $this->store ?? new Store($this->cacheDir ?: $this->kernel->getCacheDir().'/http_cache');
    }
}
