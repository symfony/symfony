<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 11/12/16
 * Time: 9:53 PM
 */

namespace Symfony\Component\Profiler\Summary;

use Symfony\Component\Profiler\DataCollector\SummaryCollectorInterface;
use Symfony\Component\Profiler\Profile;

class SummaryGenerator
{
    /** @var SummaryCollectorInterface[] */
    protected $collectors = array();

    public function generate(Profile $profile)
    {
        $summary = array(

        );

        foreach ($this->collectors as $collector) {
            $summary = array_merge($summary, $collector->getSummary($profile));
        }

        return $summary;
    }

    public function addCollector(SummaryCollectorInterface $collector)
    {
        $this->collectors[] = $collector;
        return $this;
    }
}