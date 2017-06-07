<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

/**
 * RemoteLoaderInterface is the interface implemented by all translation
 * loaders, that do not use files located in project resources.
 *
 * @author Dariusz Górecki <darek.krk@gmail.com>
 */
interface RemoteLoaderInterface extends LoaderInterface
{
    /**
     * Returns array of available resources handled by this loader.
     * eg: array of Entity managers, or database connections to use,
     * this resources will be passed to load method.
     *
     * @return mixed List of resources
     */
    function getRemoteResources();

    /**
     * Returns list of available locales in given resource.
     *
     * @param  mixed $resource Resource to scan for message domains
     * @return mixed           List of found locales
     */
    function getLocalesForResource($resource);

    /**
     * Returns list of available translation domains in given resource.
     *
     * @param  mixed  $resource Resource to scan for message domains
     * @param  string $locale   Locale to scan for message domains
     * @return mixed            List of found translation domains
     */
    function getDomainsForLocale($resource, $locale);
}
