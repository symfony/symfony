<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Loader;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
interface ImportingLoaderInterface extends LoaderInterface
{
    /**
     * Imports a resource.
     *
     * @param mixed       $resource     A Resource
     * @param string|null $type         The resource type or null if unknown
     * @param bool        $ignoreErrors Whether to ignore import errors or not
     *
     * @return mixed
     */
    public function import($resource, $type = null, $ignoreErrors = false);
}
