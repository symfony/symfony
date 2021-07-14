<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\Factory\Cache;

use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeInterface;

/**
 * A template decorator for static {@see ChoiceType} options.
 *
 * Used as fly weight for {@see CachingFactoryDecorator}.
 *
 * @internal
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
abstract class AbstractStaticOption
{
    private static array $options = [];

    private bool|string|array|\Closure|ChoiceLoaderInterface $option;

    /**
     * @param mixed $option Any pseudo callable, array, string or bool to define a choice list option
     * @param mixed $vary   Dynamic data used to compute a unique hash when caching the option
     */
    final public function __construct(FormTypeInterface|FormTypeExtensionInterface $formType, mixed $option, mixed $vary = null)
    {
        $hash = CachingFactoryDecorator::generateHash([static::class, $formType, $vary]);

        $this->option = self::$options[$hash] ??= $option instanceof \Closure || !\is_callable($option) ? $option : \Closure::fromCallable($option);
    }

    final public function getOption(): mixed
    {
        return $this->option;
    }

    final public static function reset(): void
    {
        self::$options = [];
    }
}
