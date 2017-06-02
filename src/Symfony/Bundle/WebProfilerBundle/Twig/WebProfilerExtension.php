<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Twig;

use Symfony\Component\HttpKernel\DataCollector\Util\ValueExporter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for the profiler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class WebProfilerExtension extends AbstractExtension
{
    /**
     * @var ValueExporter
     */
    private $valueExporter;

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new TwigFunction('profiler_dump', array($this, 'dumpValue')),
        );
    }

    public function dumpValue($value)
    {
        if (null === $this->valueExporter) {
            $this->valueExporter = new ValueExporter();
        }

        return $this->valueExporter->exportValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'profiler';
    }
}
