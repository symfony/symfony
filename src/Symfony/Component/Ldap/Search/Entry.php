<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Search;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Entry
{
    private $dn;
    private $attributes;
    private $snapshot;

    /**
     * Constructor.
     *
     * @param $dn
     * @param array $attributes
     */
    public function __construct($dn, array $attributes = array())
    {
        $this->dn = $dn;
        $this->attributes = $attributes;
        $this->snapshot = $attributes;
    }

    public function getAttribute($name)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }
}
