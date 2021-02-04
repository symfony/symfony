<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

/**
 * ComposerResource tracks the PHP version and Composer dependencies.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final since Symfony 4.3
 */
class ComposerResource implements SelfCheckingResourceInterface
{
    private $vendors;

    private static $runtimeVendors;

    public function __construct()
    {
        self::refresh();
        $this->vendors = self::$runtimeVendors;
    }

    public function getVendors()
    {
        return array_keys($this->vendors);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return __CLASS__;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($timestamp)
    {
        self::refresh();

        return array_values(self::$runtimeVendors) === array_values($this->vendors);
    }

    private static function refresh()
    {
        self::$runtimeVendors = [];

        foreach (get_declared_classes() as $class) {
            if ('C' === $class[0] && 0 === strpos($class, 'ComposerAutoloaderInit')) {
                $r = new \ReflectionClass($class);
                $v = \dirname($r->getFileName(), 2);
                // When using `symfony/phpunit-bridge` package, it installs a new
                // package in `vendor/bin/.phpunit`. This package comes with its
                // own autoloader. When booting symfony with `simple-phpunit`, the
                // autoloader is used and discovered here. But we do not want to
                // affect the container freshness for this package.
                if (false !== strpos($v, 'vendor/bin/.phpunit/phpunit')) {
                    continue;
                }
                if (file_exists($v.'/composer/installed.json')) {
                    self::$runtimeVendors[$v] = @filemtime($v.'/composer/installed.json');
                }
            }
        }
    }
}
