<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\Util\ValueExporter;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Caster\LinkStub;
use Symfony\Component\VarDumper\Caster\StubCaster;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * DataCollector.
 *
 * Children of this class must store the collected data in the data property.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@symfony.com>
 */
abstract class DataCollector implements DataCollectorInterface, \Serializable
{
    protected $data = array();

    /**
     * @var ValueExporter
     */
    private $valueExporter;

    /**
     * @var ClonerInterface
     */
    private $cloner;

    private static $stubsCache = array();

    public function serialize()
    {
        return serialize($this->data);
    }

    public function unserialize($data)
    {
        $this->data = unserialize($data);
    }

    /**
     * Converts the variable into a serializable Data instance.
     *
     * This array can be displayed in the template using
     * the VarDumper component.
     *
     * @param mixed $var
     *
     * @return Data
     */
    protected function cloneVar($var)
    {
        if (null === $this->cloner) {
            if (class_exists(ClassStub::class)) {
                $this->cloner = new VarCloner();
                $this->cloner->setMaxItems(250);
                $this->cloner->addCasters(array(
                    Stub::class => function (Stub $v, array $a, Stub $s, $isNested) {
                        return $isNested ? $a : StubCaster::castStub($v, $a, $s, true);
                    },
                ));
            } else {
                @trigger_error(sprintf('Using the %s() method without the VarDumper component is deprecated since version 3.2 and won\'t be supported in 4.0. Install symfony/var-dumper version 3.2 or above.', __METHOD__), E_USER_DEPRECATED);
                $this->cloner = false;
            }
        }
        if (false === $this->cloner) {
            if (null === $this->valueExporter) {
                $this->valueExporter = new ValueExporter();
            }

            return $this->valueExporter->exportValue($var);
        }

        return $this->cloner->cloneVar($this->decorateVar($var));
    }

    /**
     * Converts a PHP variable to a string.
     *
     * @param mixed $var A PHP variable
     *
     * @return string The string representation of the variable
     *
     * @deprecated Deprecated since version 3.2, to be removed in 4.0. Use cloneVar() instead.
     */
    protected function varToString($var)
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.2 and will be removed in 4.0. Use cloneVar() instead.', __METHOD__), E_USER_DEPRECATED);

        if (null === $this->valueExporter) {
            $this->valueExporter = new ValueExporter();
        }

        return $this->valueExporter->exportValue($var);
    }

    private function decorateVar($var)
    {
        if (is_array($var)) {
            if (isset($var[0], $var[1]) && is_callable($var)) {
                return ClassStub::wrapCallable($var);
            }
            foreach ($var as $k => $v) {
                if ($v !== $d = $this->decorateVar($v)) {
                    $var[$k] = $d;
                }
            }

            return $var;
        }
        if (is_string($var)) {
            if (isset(self::$stubsCache[$var])) {
                return self::$stubsCache[$var];
            }
            if (false !== strpos($var, '\\')) {
                $c = (false !== $i = strpos($var, '::')) ? substr($var, 0, $i) : $var;
                if (class_exists($c, false) || interface_exists($c, false) || trait_exists($c, false)) {
                    return self::$stubsCache[$var] = new ClassStub($var);
                }
            }
            if (false !== strpos($var, DIRECTORY_SEPARATOR) && false === strpos($var, '://') && false === strpos($var, "\0") && @is_file($var)) {
                return self::$stubsCache[$var] = new LinkStub($var);
            }
        }

        return $var;
    }
}
