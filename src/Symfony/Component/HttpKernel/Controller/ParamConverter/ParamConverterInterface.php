<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ParamConverter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\ParamConverter;

/**
 * Converts request parameters to objects and stores them as request
 * attributes, so they can be injected as controller method arguments.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ParamConverterInterface
{
    /**
     * Stores the object in the request.
     *
     * @param ParamConverter $configuration Contains the name, class and options of the object
     *
     * @return bool True if the object has been successfully set, else false
     */
    public function apply(Request $request, ParamConverter $configuration);

    /**
     * Checks if the object is supported.
     *
     * @return bool True if the object is supported, else false
     */
    public function supports(ParamConverter $configuration);
}
