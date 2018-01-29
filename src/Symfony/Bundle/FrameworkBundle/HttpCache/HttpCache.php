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

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\HttpCache\HttpCache as BaseHttpCache;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manages HTTP cache objects in a Container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class HttpCache extends BaseHttpCache
{
    protected $cacheDir;
    protected $kernel;

    /**
     * @param HttpKernelInterface $kernel   An HttpKernelInterface instance
     * @param string              $cacheDir The cache directory (default used if null)
     */
    public function __construct(HttpKernelInterface $kernel, $cacheDir = null)
    {
        $this->kernel = $kernel;
        $this->cacheDir = $cacheDir;

        parent::__construct($kernel, $this->createStore(), $this->createSurrogate(), array_merge(array('debug' => $kernel->isDebug()), $this->getOptions()));
    }

    /**
     * Forwards the Request to the backend and returns the Response.
     *
     * @param Request  $request A Request instance
     * @param bool     $raw     Whether to catch exceptions or not
     * @param Response $entry   A Response instance (the stale entry if present, null otherwise)
     *
     * @return Response A Response instance
     */
    protected function forward(Request $request, $raw = false, Response $entry = null)
    {
        $this->getKernel()->boot();
        $container = $this->getKernel()->getContainer();
        $container->set('cache', $this);
        $container->set($this->getSurrogate()->getName(), $this->getSurrogate());

        return parent::forward($request, $raw, $entry);
    }

    /**
     * Returns an array of options to customize the Cache configuration.
     *
     * @return array An array of options
     */
    protected function getOptions()
    {
        return array();
    }

    protected function createSurrogate()
    {
        return new Esi();
    }

    /**
     * Creates new ESI instance.
     *
     * @return Esi
     *
     * @deprecated since version 2.6, to be removed in 3.0. Use createSurrogate() instead
     */
    protected function createEsi()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.6 and will be removed in 3.0. Use createSurrogate() instead.', E_USER_DEPRECATED);

        return $this->createSurrogate();
    }

    protected function createStore()
    {
        return new Store($this->cacheDir ?: $this->kernel->getCacheDir().'/http_cache');
    }
}
