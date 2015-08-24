<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TwigDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TwigDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private $profile;
    private $computed;

    public function __construct(\Twig_Profiler_Profile $profile)
    {
        $this->profile = $profile;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        $this->data['profile'] = serialize($this->profile);
    }

    public function getTime()
    {
        return $this->getProfile()->getDuration() * 1000;
    }

    public function getTemplateCount()
    {
        return $this->getComputedData('template_count');
    }

    public function getTemplates()
    {
        return $this->getComputedData('templates');
    }

    public function getBlockCount()
    {
        return $this->getComputedData('block_count');
    }

    public function getMacroCount()
    {
        return $this->getComputedData('macro_count');
    }

    public function getHtmlCallGraph()
    {
        $dumper = new \Twig_Profiler_Dumper_Html();

        return new \Twig_Markup($dumper->dump($this->getProfile()), 'UTF-8');
    }

    public function getProfile()
    {
        if (null === $this->profile) {
            $this->profile = unserialize($this->data['profile']);
        }

        return $this->profile;
    }

    private function getComputedData($index)
    {
        if (null === $this->computed) {
            $this->computed = $this->computeData($this->getProfile());
        }

        return $this->computed[$index];
    }

    private function computeData(\Twig_Profiler_Profile $profile)
    {
        $data = array(
            'template_count' => 0,
            'block_count' => 0,
            'macro_count' => 0,
        );

        $templates = array();
        foreach ($profile as $p) {
            $d = $this->computeData($p);

            $data['template_count'] += ($p->isTemplate() ? 1 : 0) + $d['template_count'];
            $data['block_count'] += ($p->isBlock() ? 1 : 0) + $d['block_count'];
            $data['macro_count'] += ($p->isMacro() ? 1 : 0) + $d['macro_count'];

            if ($p->isTemplate()) {
                if (!isset($templates[$p->getTemplate()])) {
                    $templates[$p->getTemplate()] = 1;
                } else {
                    ++$templates[$p->getTemplate()];
                }
            }

            foreach ($d['templates'] as $template => $count) {
                if (!isset($templates[$template])) {
                    $templates[$template] = $count;
                } else {
                    $templates[$template] += $count;
                }
            }
        }
        $data['templates'] = $templates;

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'twig';
    }
}
