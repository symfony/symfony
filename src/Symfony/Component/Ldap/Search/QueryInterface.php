<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
namespace Symfony\Component\Ldap\Search;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
interface QueryInterface
{
    /**
     * Executes a query and returns the list of Ldap entries
     *
     * @return Collection|Entry[]
     */
    public function execute();

    /**
     * Fetches the ldap search resource.
     *
     * @return resource
     */
    public function getResource();
}
