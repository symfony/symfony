<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Stopwatch\Section;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchPeriod;

/**
 * ServerTimingListener exposes collected metrics in the response's headers following the server timing specifications.
 *
 * @see https://www.w3.org/TR/server-timing/
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class ServerTimingListener implements EventSubscriberInterface
{
    private $stopwatch;

    public function __construct(Stopwatch $stopwatch = null)
    {
        $this->stopwatch = $stopwatch;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (null === $this->stopwatch || !$event->isMasterRequest()) {
            return;
        }
        $response = $event->getResponse();
        $durationByCategory = $this->computeTiming();

        $timing = array();
        \arsort($durationByCategory);
        $i = 0;
        foreach ($durationByCategory as $name => $duration) {
            // prefix the metric's name with a counter to force browser to keep the order
            $timing[] = sprintf('%s;dur=%.3f;desc="%s"', sprintf('%\'.03d-%s', $i++, \preg_replace('/[^a-z0-9#_!#$%&\'*+.^_`|~-]/i', '-', $name)), $duration, \addslashes($name));
        }

        $response->headers->set('Server-Timing', implode(',', $timing));
    }

    protected function computeTiming()
    {
        // Collect list of periods per category
        $periodsByCategory = array();
        foreach ($this->stopwatch->getSections() as $section) {
            $periodsByCategory = \array_merge_recursive($periodsByCategory, $this->collectPeriods($section));
        }

        $durationByCategory = array();
        // Remove dupplicate period in the same time frame and compute duration
        foreach ($periodsByCategory as $category => $periods) {
            $duration = 0;
            \usort($periods, function (StopwatchPeriod $a, StopwatchPeriod $b) {
                return array($a->getStartTime(), $a->getEndTime()) <=> array($b->getStartTime(), $b->getEndTime());
            });
            $lastPeriod = null;
            foreach ($periods as $period) {
                if (null !== $lastPeriod && $lastPeriod->getEndTime() > $period->getStartTime()) {
                    if ($period->getEndTime() <= $lastPeriod->getEndTime()) {
                        continue;
                    }

                    $period = new StopwatchPeriod($lastPeriod->getEndTime(), $period->getEndTime(), true);
                }

                $duration += $period->getDuration();
                $lastPeriod = $period;
            }
            $durationByCategory[$category] = $duration;
        }

        return $durationByCategory;
    }

    private function collectPeriods(Section $section)
    {
        $periods = array();
        foreach ($section->getEvents() as $event) {
            if (!isset($periods[$category = $event->getCategory()])) {
                $periods[$category] = $event->getPeriods();
            } else {
                $periods[$category] = array_merge($periods[$event->getCategory()], $event->getPeriods());
            }
        }

        foreach ($section->getChildren() as $child) {
            $periods = \array_merge_recursive($periods, $this->collectPeriods($child));
        }

        return $periods;
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => array('onKernelResponse', -128),
        );
    }
}
