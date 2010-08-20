<?php

namespace Symfony\Component\HttpKernel\Profiler\DataCollector;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * MemoryDataCollector.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class MemoryDataCollector extends DataCollector
{
    public function collect()
    {
        $this->data = array(
            'memory' => memory_get_peak_usage(true),
        );
    }

    public function getMemory()
    {
        return $this->data['memory'];
    }

    public function getSummary()
    {
        return sprintf('<img style="margin-left: 10px; vertical-align: middle" alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAACXBIWXMAAAsTAAALEwEAmpwYAAAABGdBTUEAALGOfPtRkwAAACBjSFJNAAB6JQAAgIMAAPn/AACA6QAAdTAAAOpgAAA6mAAAF2+SX8VGAAAAmElEQVR42sSTuwoEIQxFr7JtOgf0n+z9Rhsr/0nB0jKiW00xs7Myj4VNFQj3cAKJGGPgSUk8rNfaeO8vqTjnxAYAAMuynAqXUj4NAKDWen8FKSWstadCIYRjg9YaUkrTsDHm2GAFtNamgP18A2BmKKWmAGaeG+ScpwCt9XdA7x299ylgP//dJQIAEYGI7gNijJcNxN+/8T0A1+E5NmcLfJkAAAAASUVORK5CYII=" />
            %.0f KB
        ', $this->data['memory'] / 1024);
    }

    public function getName()
    {
        return 'memory';
    }
}
