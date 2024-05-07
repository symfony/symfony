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

use Symfony\Bridge\Twig\UndefinedCallableHandler;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * Twig environment configurator.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class EnvironmentConfigurator
{
    private string $dateFormat;
    private string $intervalFormat;
    private ?string $timezone;
    private int $decimals;
    private string $decimalPoint;
    private string $thousandsSeparator;

    public function __construct(string $dateFormat, string $intervalFormat, ?string $timezone, int $decimals, string $decimalPoint, string $thousandsSeparator)
    {
        $this->dateFormat = $dateFormat;
        $this->intervalFormat = $intervalFormat;
        $this->timezone = $timezone;
        $this->decimals = $decimals;
        $this->decimalPoint = $decimalPoint;
        $this->thousandsSeparator = $thousandsSeparator;
    }

    public function configure(Environment $environment): void
    {
        $environment->getExtension(CoreExtension::class)->setDateFormat($this->dateFormat, $this->intervalFormat);

        if (null !== $this->timezone) {
            $environment->getExtension(CoreExtension::class)->setTimezone($this->timezone);
        }

        $environment->getExtension(CoreExtension::class)->setNumberFormat($this->decimals, $this->decimalPoint, $this->thousandsSeparator);

        // wrap UndefinedCallableHandler in closures for lazy-autoloading
        $environment->registerUndefinedFilterCallback(fn ($name) => UndefinedCallableHandler::onUndefinedFilter($name));
        $environment->registerUndefinedFunctionCallback(fn ($name) => UndefinedCallableHandler::onUndefinedFunction($name));
    }
}
