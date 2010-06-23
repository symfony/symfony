<?php

namespace Symfony\Foundation\Cache;

use Symfony\Components\HttpKernel\HttpKernelInterface;
use Symfony\Components\HttpKernel\Cache\Cache as BaseCache;
use Symfony\Components\HttpKernel\Cache\Esi;
use Symfony\Components\HttpKernel\Cache\Store;
use Symfony\Components\HttpKernel\Request;
use Symfony\Components\HttpKernel\Response;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * @package    Symfony
 * @subpackage Foundation
 * @author     Fabien Potencier <fabien.potencier@symfony-project.org>
 */
abstract class Cache extends BaseCache
{
    /**
     * Constructor.
     *
     * @param Symfony\Components\HttpKernel\HttpKernelInterface $kernel An HttpKernelInterface instance
     */
    public function __construct(HttpKernelInterface $kernel)
    {
        $this->store = new Store($kernel->getCacheDir().'/http_cache');
        $esi = new Esi();

        parent::__construct($kernel, $this->store, $esi, array_merge(array('debug' => $kernel->isDebug()), $this->getOptions()));
    }

    /**
     * Forwards the Request to the backend and returns the Response.
     *
     * @param Symfony\Components\HttpKernel\Request  $request  A Request instance
     * @param Boolean                                $raw      Whether to catch exceptions or not
     * @param Symfony\Components\HttpKernel\Response $response A Response instance (the stale entry if present, null otherwise)
     *
     * @return Symfony\Components\HttpKernel\Response A Response instance
     */
    protected function forward(Request $request, $raw = false, Response $entry = null)
    {
        if (!$this->kernel->isBooted()) {
            $this->kernel->boot();
        }
        $this->kernel->getContainer()->setService('cache', $this);
        $this->kernel->getContainer()->setService('esi', $this->esi);

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
