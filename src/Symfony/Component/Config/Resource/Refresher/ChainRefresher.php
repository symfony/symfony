<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource\Refresher;

use Symfony\Component\Config\Resource\MutableResourceInterface;

/**
 * @author Luc Vieillescazes <luc@vieillescazes.net>
 */
class ChainRefresher implements RefresherInterface
{
    private $refreshers = array();

    public function addRefresher(RefresherInterface $refresher)
    {
        $this->refreshers[] = $refresher;
    }

    public function refresh(MutableResourceInterface $resource)
    {
        foreach ($this->refreshers as $refresher) {
            $refresher->refresh($resource);
        }
    }
}
