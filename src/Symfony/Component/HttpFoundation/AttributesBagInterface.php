<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\AttributesBagInterface;
use Symfony\Component\HttpFoundation\SessionStorage\AttributeInterface;

/**
 * Attributes store.
 *
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
interface AttributesBagInterface extends AttributeInterface
{
    /**
     * Initializes the AttributesBag
     *
     * @param array $attributes
     */
    public function initialize(array &$attributes);

    /**
     * Gets the storage key for this bag.
     *
     * @return string
     */
    function getStorageKey();
}
