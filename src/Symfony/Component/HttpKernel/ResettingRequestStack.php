<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resets services on second master requests.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResettingRequestStack extends RequestStack
{
    private $resetNeeded = false;
    private $resettableServices;
    private $resetMethods;

    public function __construct(\Traversable $resettableServices, array $resetMethods)
    {
        $this->resettableServices = $resettableServices;
        $this->resetMethods = $resetMethods;
    }

    /**
     * {@inheritdoc}
     */
    public function push(Request $request)
    {
        if ($this->resetNeeded && !$this->getCurrentRequest()) {
            foreach ($this->resettableServices as $id => $service) {
                $service->{$this->resetMethods[$id]}();
            }
        } else {
            $this->resetNeeded = true;
        }

        parent::push($request);
    }
}
