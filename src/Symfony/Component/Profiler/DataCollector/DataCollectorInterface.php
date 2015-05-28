<?php


namespace Symfony\Component\Profiler\DataCollector;


interface DataCollectorInterface
{
    public function setToken($token);

    /**
     * Returns the name of the collector.
     *
     * @return string The collector name
     *
     * @api
     */
    public function getName();
}