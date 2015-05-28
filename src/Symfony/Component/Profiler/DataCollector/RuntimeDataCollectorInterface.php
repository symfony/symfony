<?php


namespace Symfony\Component\Profiler\DataCollector;


interface RuntimeDataCollectorInterface {
    /**
     * Collects data when profiler is triggered.
     */
    public function collect();
}