<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Resolves the value of a controller argument from its metadata and the request.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface ValueResolverInterface
{
    /**
     * Returns the possible value(s).
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable;
}
