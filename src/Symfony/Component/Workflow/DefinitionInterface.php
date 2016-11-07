<?php
namespace Symfony\Component\Workflow;


/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface DefinitionInterface
{
    /**
     * Get the initial place
     *
     * @return string
     */
    public function getInitialPlace();

    /**
     * Get all the places
     *
     * @return string[]
     */
    public function getPlaces();

    /**
     * @return Transition[]
     */
    public function getTransitions();
}