<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 11/12/16
 * Time: 9:39 PM
 */

namespace Symfony\Component\Profiler\DataCollector;

use Symfony\Component\Profiler\Profile;

interface SummaryCollectorInterface
{
    public function getSummary(Profile $profile);
    public function getSummaryKeys();
}
