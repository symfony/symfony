<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface DefinitionInterface
{
    /**
     * Get the initial place.
     *
     * @return string
     */
    public function getInitialPlace();

    /**
     * Get all the places.
     *
     * @return string[]
     */
    public function getPlaces();

    /**
     * @return Transition[]
     */
    public function getTransitions();
}
