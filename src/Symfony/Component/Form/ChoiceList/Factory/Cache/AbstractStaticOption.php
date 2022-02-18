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
    private static $options = [];
    private static $canaryMap;

    /** @var bool|callable|string|array|\Closure|ChoiceLoaderInterface */
    private $option;

    /**
     * @param FormTypeInterface|FormTypeExtensionInterface $formType A form type or type extension configuring a cacheable choice list
     * @param mixed                                        $option   Any pseudo callable, array, string or bool to define a choice list option
     * @param mixed|null                                   $vary     Dynamic data used to compute a unique hash when caching the option
     */
    final public function __construct($formType, $option, $vary = null)
    {
        if (!$formType instanceof FormTypeInterface && !$formType instanceof FormTypeExtensionInterface) {
            throw new \TypeError(sprintf('Expected an instance of "%s" or "%s", but got "%s".', FormTypeInterface::class, FormTypeExtensionInterface::class, get_debug_type($formType)));
        }

        $canary = null;
        $key = [static::class, $formType, $vary];
        array_walk_recursive($key, static function (&$v) use (&$canary) {
            if (!\is_object($v)) {
                return;
            }

            if (\PHP_VERSION_ID < 80000) {
                $v = spl_object_hash($v);

                return;
            }

            self::$canaryMap = self::$canaryMap ?? new \WeakMap();
            $canary = $canary ?? new class () {
                public $hash = '';
                public $options = [];

                public function __destruct()
                {
                    unset($this->options[$this->hash]);
                }
            };

            if (isset(self::$canaryMap[$k = $v])) {
                self::$canaryMap[$k][] = $canary;
            } else {
                self::$canaryMap[$k] = [$canary];
            }

            $v = spl_object_hash($v);
        });
        $hash = hash('sha256', ':'.serialize($key));

        if ($canary) {
            $canary->hash = $hash;
            $canary->options =& self::$options;
        }

        $this->option = self::$options[$hash] ?? self::$options[$hash] = $option;
    }

    /**
     * @return mixed
     */
    final public function getOption()
    {
        return $this->option;
    }

    final public static function reset(): void
    {
        self::$options = [];
        self::$canaryMap = null;
    }
}
