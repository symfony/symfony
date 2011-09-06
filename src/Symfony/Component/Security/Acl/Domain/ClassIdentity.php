<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Domain;

/**
 * An helper class with a getClass method aware of proxies
 *
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 */
class ClassIdentity
{
    /**
     * @var array An array of proxies interfaces for which
     *            we want to retrieve the parent class
     */
    protected static $interfaces = array(
        'Doctrine\ORM\Proxy\Proxy',
        'Doctrine\ODM\MongoDB\Proxy\Proxy',
        'Doctrine\ODM\CouchDB\Proxy\Proxy',
        'Doctrine\ODM\PHPCR\Proxy\Proxy',
        'Doctrine\OXM\Proxy\Proxy',
    );

    /**
     * Returns the class of the domain object $object
     *
     * If $object is a proxy instance, gets its real class.
     *
     * @param object $object The object we want to know the real class of
     * @return string The class of the domain object
     */
    public static function getClass($object)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException('$object must be an object.');
        }

        foreach (static::$interfaces as $interface) {
            if ($object instanceof $interface) {
                return get_parent_class($object);
            }
        }

        return get_class($object);
    }
}
