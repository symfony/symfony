<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\DataCollector;

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
     * @var ClonerInterface
     */
    private $cloner;

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
            $this->cloner = new VarCloner();
            $this->cloner->setMaxItems(250);
            $this->cloner->addCasters(array(
                Stub::class => function (Stub $v, array $a, Stub $s, $isNested) {
                    return $isNested ? $a : StubCaster::castStub($v, $a, $s, true);
                },
            ));
        }

        return $this->cloner->cloneVar($this->decorateVar($var));
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
            if (false !== strpos($var, '\\')) {
                $c = (false !== $i = strpos($var, '::')) ? substr($var, 0, $i) : $var;
                if (class_exists($c, false) || interface_exists($c, false) || trait_exists($c, false)) {
                    return new ClassStub($var);
                }
            }
            if (false !== strpos($var, DIRECTORY_SEPARATOR) && file_exists($var)) {
                return new LinkStub($var);
            }
        }

        return $var;
    }
}
