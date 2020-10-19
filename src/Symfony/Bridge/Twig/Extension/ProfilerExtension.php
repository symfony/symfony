<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Stopwatch\Stopwatch;
use Twig\Extension\ProfilerExtension as BaseProfilerExtension;
use Twig\Profiler\Profile;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since Symfony 4.4
 */
class ProfilerExtension extends BaseProfilerExtension
{
    private $stopwatch;
    private $events;

    public function __construct(Profile $profile, Stopwatch $stopwatch = null)
    {
        parent::__construct($profile);

        $this->stopwatch = $stopwatch;
        $this->events = new \SplObjectStorage();
    }

    /**
     * @return void
     */
    public function enter(Profile $profile)
    {
        if ($this->stopwatch && $profile->isTemplate()) {
            $this->events[$profile] = $this->stopwatch->start($profile->getName(), 'template');
        }

        parent::enter($profile);
    }

    /**
     * @return void
     */
    public function leave(Profile $profile)
    {
        parent::leave($profile);

        if ($this->stopwatch && $profile->isTemplate()) {
            $this->events[$profile]->stop();
            unset($this->events[$profile]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'native_profiler';
    }
}
