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

    /**
     * @param string $cacheDir The cache directory (default used if null)
     */
    public function __construct(KernelInterface $kernel, string $cacheDir = null)
    {
        $this->kernel = $kernel;
        $this->cacheDir = $cacheDir;

        $isDebug = $kernel->isDebug();
        $options = ['debug' => $isDebug];

        if ($isDebug) {
            $options['stale_if_error'] = 0;
        }

        parent::__construct($kernel, $this->createStore(), $this->createSurrogate(), array_merge($options, $this->getOptions()));
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
     * @return array An array of options
     */
    protected function getOptions()
    {
        return [];
    }

    protected function createSurrogate()
    {
        return new Esi();
    }

    protected function createStore()
    {
        return new Store($this->cacheDir ?: $this->kernel->getCacheDir().'/http_cache');
    }
}
