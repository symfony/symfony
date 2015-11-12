<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler;

use Symfony\Component\Profiler\ProfileData\ProfileDataInterface;
use Symfony\Component\Profiler\ProfileData\TokenAwareProfileDataInterface;

/**
 * Profile.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class Profile
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var int
     */
    private $time;

    /**
     * @var ProfileDataInterface[]
     */
    private $data = array();

    /**
     * @var Profile
     */
    private $parent;

    /**
     * @var Profile[]
     */
    private $children = array();

    /**
     * @var array
     */
    private $indexes;

    /**
     * Constructor.
     *
     * @param string $token     The token
     * @param int    $time      The time
     * @param array  $indexes
     */
    public function __construct($token, $time = null, $indexes = array())
    {
        $this->token = $token;
        $this->time = null === $time ? time() : $time;
        $this->indexes = $indexes;
    }

    /**
     * Returns the token.
     *
     * @return string The token
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Sets the parent token.
     *
     * @param Profile $parent The parent Profile
     */
    public function setParent(Profile $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Returns the parent profile.
     *
     * @return Profile The parent profile
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Returns the parent token.
     *
     * @return null|string The parent token
     */
    public function getParentToken()
    {
        return $this->parent ? $this->parent->getToken() : null;
    }

    /**
     * Returns the time.
     *
     * @return string The time
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * Returns children profiles.
     *
     * @return Profile[] An array of Profile
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Sets children profiler.
     *
     * @param Profile[] $children An array of Profile
     */
    public function setChildren(array $children)
    {
        $this->children = array();
        foreach ($children as $child) {
            $this->addChild($child);
        }
    }

    /**
     * Adds the child token.
     *
     * @param Profile $child The child Profile
     */
    public function addChild(Profile $child)
    {
        $this->children[] = $child;
        $child->setParent($this);
    }

    /**
     * Returns the collection of profile data.
     *
     * @return ProfileDataInterface[]
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Sets the ProfileData associated with this profile.
     *
     * @param ProfileDataInterface[] $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * Adds a Collector.
     *
     * @param ProfileDataInterface $profileData A ProfileDataInterface instance
     */
    public function add(ProfileDataInterface $profileData = null)
    {
        if ( null === $profileData ) {
            return;
        }
        if ( $profileData instanceof TokenAwareProfileDataInterface ) {
            $profileData->setToken($this->token);
        }
        $this->data[$profileData->getName()] = $profileData;
    }

    /**
     * Returns data for a specific section.
     *
     * @param $name
     *
     * @return ProfileDataInterface
     */
    public function get($name)
    {
        if (!isset($this->data[$name])) {
            throw new \InvalidArgumentException(sprintf('ProfileData "%s" does not exist.', $name));
        }

        return $this->data[$name];
    }

    /**
     * Check of data exists for a specific section.
     *
     * @param $name
     *
     * @return bool
     */
    public function has($name)
    {
        return isset($this->data[$name]);
    }

    public function __call($name, $arguments)
    {
        if (substr($name, 0, 3) == 'get') {
            $property = ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', strtolower(substr($name, 3, 1)) . substr($name, 4))), '_');
            if ( isset($this->indexes[$property]) ) {
                return $this->indexes[$property];
            }
        }
    }

    public function getIndex($name)
    {
        if ( !isset($this->indexes[$name]) ) {
            return;
        }
        return $this->indexes[$name];
    }

    /**
     * @return array
     *
     * @api
     */
    public function getIndexes()
    {
        return $this->indexes;
    }
}
