<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Configurator;

/**
 * Twig environment configurator.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class EnvironmentConfigurator
{
    private $dateFormat;
    private $intervalFormat;
    private $timezone;
    private $decimals;
    private $decimalPoint;
    private $thousandsSeparator;

    public function __construct($dateFormat, $intervalFormat, $timezone, $decimals, $decimalPoint, $thousandsSeparator)
    {
        $this->dateFormat = $dateFormat;
        $this->intervalFormat = $intervalFormat;
        $this->timezone = $timezone;
        $this->decimals = $decimals;
        $this->decimalPoint = $decimalPoint;
        $this->thousandsSeparator = $thousandsSeparator;
    }

    public function configure(\Twig_Environment $environment)
    {
        $environment->getExtension('Twig_Extension_Core')->setDateFormat($this->dateFormat, $this->intervalFormat);

        if (null !== $this->timezone) {
            $environment->getExtension('Twig_Extension_Core')->setTimezone($this->timezone);
        }

        $environment->getExtension('Twig_Extension_Core')->setNumberFormat($this->decimals, $this->decimalPoint, $this->thousandsSeparator);
    }
}
