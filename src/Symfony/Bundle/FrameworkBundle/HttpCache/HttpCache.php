<?php

namespace Symfony\Bundle\FrameworkBundle\HttpCache;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\HttpCache\HttpCache as BaseHttpCache;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Manages HTTP cache objects in a Container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class HttpCache extends BaseHttpCache
{
    /**
     * Constructor.
     *
     * @param HttpKernelInterface $kernel An HttpKernelInterface instance
     */
    public function __construct(HttpKernelInterface $kernel)
    {
        $store = new Store($kernel->getCacheDir().'/http_cache');
        $esi = new Esi();

        parent::__construct($kernel, $store, $esi, array_merge(array('debug' => $kernel->isDebug()), $this->getOptions()));
    }

    /**
     * Forwards the Request to the backend and returns the Response.
     *
     * @param Request  $request A Request instance
     * @param Boolean  $raw     Whether to catch exceptions or not
     * @param Response $entry   A Response instance (the stale entry if present, null otherwise)
     *
     * @return Response A Response instance
     */
    protected function forward(Request $request, $raw = false, Response $entry = null)
    {
        $this->getKernel()->boot();
        $this->getKernel()->getContainer()->set('cache', $this);
        $this->getKernel()->getContainer()->set('esi', $this->getEsi());

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
}
