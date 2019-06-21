<?php

namespace Symfony\Component\Lock;

interface LockableInterface
{
    /**
     * Sets the current store.
     *
     * @param StoreInterface $store The store to be used
     */
    public function setStore(StoreInterface $store);
}
