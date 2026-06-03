<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Triggered whenever a request is fully processed.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
final class FinishRequestEvent extends KernelEvent
{
    public function __construct(
        HttpKernelInterface $kernel,
        Request $request,
        ?int $requestType,
        public readonly ?ControllerMetadata $controllerMetadata = null,
    ) {
        parent::__construct($kernel, $request, $requestType);
    }
}
