<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\StateChecker\Inotify;

/**
 * Topmost directory state checker. Top directory - folder that was provided to InotifyTracker::track method.
 *
 * @author Yaroslav Kiliba <om.dattaya@gmail.com>
 */
class TopDirectoryStateChecker extends DirectoryStateChecker
{
    /**
     * {@inheritdoc}
     */
    protected function handleItself()
    {
        if ($this->getResource()->exists()) {
            if ($this->isMoved($this->event)) {
                if ($this->id !== ($id = $this->addWatch())) {
                    $this->unwatch($this->id);
                    $this->reindexChildCheckers();
                    if ($this->getBag()->has($id)) {
                        $this->unwatch($id);
                    }
                }

                return;
            }
            if ($this->isIgnored($this->event) || !$this->id) {
                $this->event = $this->id ? null : IN_CREATE;
                $this->reindexChildCheckers();
            }
        } elseif ($this->id) {
            $this->event = IN_DELETE;
            $this->unwatch($this->id);
        }
    }
}
