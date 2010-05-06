<?php

namespace Symfony\Framework\ProfilerBundle\DataCollector;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * AppDataCollector.
 *
 * @package    Symfony
 * @subpackage Framework_ProfilerBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AppDataCollector extends DataCollector
{
    protected function collect()
    {
        $request = $this->container->getRequestService();

        return array(
            'route'        => $request->path->get('_route') ? $request->path->get('_route') : '<span style="color: #a33">NONE</span>',
            'format'       => $request->getRequestFormat(),
            'content_type' => $this->manager->getResponse()->headers->get('Content-Type') ? $this->manager->getResponse()->headers->get('Content-Type') : 'text/html',
            'code'         => $this->manager->getResponse()->getStatusCode(),
        );
    }

    public function getSummary()
    {
        return sprintf('<img style="margin-left: 10px; vertical-align: middle" alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAACXBIWXMAAAsTAAALEwEAmpwYAAAABGdBTUEAALGOfPtRkwAAACBjSFJNAAB6JQAAgIMAAPn/AACA6QAAdTAAAOpgAAA6mAAAF2+SX8VGAAABKElEQVR42sSTPa6CQBSFv3lRrCRYACvQxMLCBhsTK5fAGtiAPaGfDbgGNmBCS0PFAqhsoSGSWE1yrTDvxd+E4t1qkrnn3HPPnFEiwpD6YWCNPjUcj0fquhYAz/MUQBRFzwniOH6cMBrJfD4HoKoq6UleKkiS5H7WWstut+N0OmHbNrZt92rURw+01rJerzmfzyyXSy6XC77vf2ei1lpWqxVN02CMwRhDEARUVcXHFfrJbdsyHo8BcByHsiwxxqjfBj4omE6nstlsuF6vWJaFZVl4nkdZlhwOB/U2B1mWSRiGFEWB67pMJhNc16Uoipfgpx6EYUie58xmM/I8fwsGUH2UsywTgMViAUCapnRdp9498x+COI5lu93eL/b7vfomyurfP9NggtsAfaVzbTWryOIAAAAASUVORK5CYII=" />
            %s<span style="margin: 0; padding: 0; color: #aaa">/</span>%s<span style="margin: 0; padding: 0; color: #aaa">/</span><span style="color: %s">%s</span><span style="margin: 0; padding: 0; color: #aaa">/</span>%s
        ', $this->data['route'], $this->data['format'], 200 == $this->data['code'] ? '#3a3' : '#a33', $this->data['code'], $this->data['content_type']);
    }

    public function getName()
    {
        return 'app';
    }
}
