<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class SessionBagProxy implements SessionBagInterface
{
    private $bag;
    private $data;
    private $hasBeenStarted;

    public function __construct(SessionBagInterface $bag, array &$data, &$hasBeenStarted)
    {
        $this->bag = $bag;
        $this->data = &$data;
        $this->hasBeenStarted = &$hasBeenStarted;
    }

    /**
     * @return SessionBagInterface
     */
    public function getBag()
    {
        return $this->bag;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->data[$this->bag->getStorageKey()]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->bag->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(array &$array)
    {
        $this->hasBeenStarted = true;
        $this->data[$this->bag->getStorageKey()] = &$array;

        $this->bag->initialize($array);
    }

    /**
     * {@inheritdoc}
     */
    public function getStorageKey()
    {
        return $this->bag->getStorageKey();
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->bag->clear();
    }
}
