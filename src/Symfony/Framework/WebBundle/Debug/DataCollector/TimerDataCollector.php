<?php

namespace Symfony\Framework\WebBundle\Debug\DataCollector;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TimerDataCollector extends DataCollector
{
  protected $data;

  public function collect()
  {
    return $this->data = array(
      'time' => microtime(true) - $this->container->getKernelService()->getStartTime(),
    );
  }

  public function getSummary()
  {
    return sprintf('<img style="margin-left: 10px; vertical-align: middle" alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAACXBIWXMAAAsTAAALEwEAmpwYAAAABGdBTUEAALGOfPtRkwAAACBjSFJNAAB6JQAAgIMAAPn/AACA6QAAdTAAAOpgAAA6mAAAF2+SX8VGAAABSElEQVR42pyTwZHCMAxFXxxKUAXuIA3gFnJxERxohAt9QAM5EhpwB6rAJdjJXmKThIWdWc1oJvbo/y8p383tdmMfMcYEtKurLCKHfZ33nsMOmAFjraXrunofQmhVdQYmEVkTvwhijPMaOI5jLXLO0XUdIQSjqrOINBuCGGMu4AL03leC+/1eiQBUNZdOzFJjCth7X8GXy6WSee8Zx7F0WHCYGGOy1la1aZpqrs/zPNcaa21ZNAZoi3rf9+ScawL1O6VE3/frLtrNEgFSSm+/9Hq9AnA+n/ktvhKcTqev5GWEHELAOccwDKSUPuYwDDjnCCEAZAAjIgdV3Sh9yhKqSnFmGWEKIRjnHI/HA4Dj8VgBz+ez+mBRnzY7EJFi1WqWvRMXSxf19m2JItKoalZVY62toDXw61sonSwzJlX98zUCNGuH/Sd+BgBGROvHb4RJ6gAAAABJRU5ErkJggg==" />
      %.0f ms
    ', $this->data['time'] * 1000);
  }

  public function getName()
  {
    return 'timer';
  }
}
