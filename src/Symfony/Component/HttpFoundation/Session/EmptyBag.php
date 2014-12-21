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

use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\Attribute\EmptyAttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Flash\EmptyFlashBag;
use Symfony\Component\HttpFoundation\Session\Storage\EmptyStorageInterface;

/**
 * Abstract base class for Empty Session bags
 *
 * @author Terje Bråten <terje@braten.be>
 */
class EmptyBag implements SessionBagInterface
{
    /**
     *  Flag if the bag is still empty
     *
     *  @var boolean $isEmpty
     */
    protected $isEmpty = true;

    /**
     * The empty storage containing this bag
     *
     * @var EmptyStorageInterface $storage
     */
    protected $storage;

    /**
     * The session bag this empty bag is a proxy for
     *
     * @var SessionBagInterface $realBag
     */
    protected $realBag;

    /**
     * Constructor.
     *
     * @param EmptyStorageInterface $storage
     * @param SessionBagInterface   $realBag
     */
    public function __construct(EmptyStorageInterface $storage, SessionBagInterface $realBag)
    {
        $this->storage = $storage;
        $this->realBag = $realBag;
    }

    /**
     * Create a new empty bag
     *
     * @param EmptyStorageInterface $storage
     * @param SessionBagInterface   $realBag
     */
    public static function create(EmptyStorageInterface $storage,
                                  SessionBagInterface $realBag)
    {
        if ($realBag instanceof AttributeBagInterface) {
            return new EmptyAttributeBag($storage, $realBag);
        }
        if ($realBag instanceof FlashBagInterface) {
            return new EmptyFlashBag($storage, $realBag);
        }

        throw new \LogicException('Unknown bag interface');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->realBag->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(array &$array)
    {
        $this->startSession();

        return $this->realBag->initialize($array);
    }

    /**
     * Start the session
     * Something has been written to the bag,
     * and the storage must be informed
     */
    protected function startSession()
    {
        if ($this->isEmpty) {
            $this->isEmpty = false;
            $this->realBag = $this->storage->getRealBag($this->getName());
        }
    }

    /**
     * Gets the storage key for this bag.
     *
     * @return string
     */
    public function getStorageKey()
    {
        return $this->realBag->getStorageKey();
    }

    /**
     * Clears out data from bag.
     *
     * @return mixed Whatever data was contained.
     */
    public function clear()
    {
        if ($this->isEmpty) {
            return array();
        }

        return $this->realBag->clear();
    }
}
