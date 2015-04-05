<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Cloner;

use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Exception\ThrowingCasterException;

/**
 * AbstractCloner implements a generic caster mechanism for objects and resources.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractCloner implements ClonerInterface
{
    public static $defaultCasters = array(
        'Symfony\Component\VarDumper\Caster\CutStub' => 'Symfony\Component\VarDumper\Caster\StubCaster::castStub',
        'Symfony\Component\VarDumper\Caster\ConstStub' => 'Symfony\Component\VarDumper\Caster\StubCaster::castStub',

        'Closure' => 'Symfony\Component\VarDumper\Caster\ReflectionCaster::castClosure',
        'ReflectionClass' => 'Symfony\Component\VarDumper\Caster\ReflectionCaster::castClass',
        'ReflectionFunctionAbstract' => 'Symfony\Component\VarDumper\Caster\ReflectionCaster::castFunctionAbstract',
        'ReflectionMethod' => 'Symfony\Component\VarDumper\Caster\ReflectionCaster::castMethod',
        'ReflectionParameter' => 'Symfony\Component\VarDumper\Caster\ReflectionCaster::castParameter',
        'ReflectionProperty' => 'Symfony\Component\VarDumper\Caster\ReflectionCaster::castProperty',
        'ReflectionExtension' => 'Symfony\Component\VarDumper\Caster\ReflectionCaster::castExtension',
        'ReflectionZendExtension' => 'Symfony\Component\VarDumper\Caster\ReflectionCaster::castZendExtension',

        'Doctrine\Common\Persistence\ObjectManager' => 'Symfony\Component\VarDumper\Caster\StubCaster::cutInternals',
        'Doctrine\Common\Proxy\Proxy' => 'Symfony\Component\VarDumper\Caster\DoctrineCaster::castCommonProxy',
        'Doctrine\ORM\Proxy\Proxy' => 'Symfony\Component\VarDumper\Caster\DoctrineCaster::castOrmProxy',
        'Doctrine\ORM\PersistentCollection' => 'Symfony\Component\VarDumper\Caster\DoctrineCaster::castPersistentCollection',

        'DOMException' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castException',
        'DOMStringList' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castLength',
        'DOMNameList' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castLength',
        'DOMImplementation' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castImplementation',
        'DOMImplementationList' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castLength',
        'DOMNode' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castNode',
        'DOMNameSpaceNode' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castNameSpaceNode',
        'DOMDocument' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castDocument',
        'DOMNodeList' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castLength',
        'DOMNamedNodeMap' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castLength',
        'DOMCharacterData' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castCharacterData',
        'DOMAttr' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castAttr',
        'DOMElement' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castElement',
        'DOMText' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castText',
        'DOMTypeinfo' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castTypeinfo',
        'DOMDomError' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castDomError',
        'DOMLocator' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castLocator',
        'DOMDocumentType' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castDocumentType',
        'DOMNotation' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castNotation',
        'DOMEntity' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castEntity',
        'DOMProcessingInstruction' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castProcessingInstruction',
        'DOMXPath' => 'Symfony\Component\VarDumper\Caster\DOMCaster::castXPath',

        'ErrorException' => 'Symfony\Component\VarDumper\Caster\ExceptionCaster::castErrorException',
        'Exception' => 'Symfony\Component\VarDumper\Caster\ExceptionCaster::castException',
        'Symfony\Component\DependencyInjection\ContainerInterface' => 'Symfony\Component\VarDumper\Caster\StubCaster::cutInternals',
        'Symfony\Component\VarDumper\Exception\ThrowingCasterException' => 'Symfony\Component\VarDumper\Caster\ExceptionCaster::castThrowingCasterException',

        'PDO' => 'Symfony\Component\VarDumper\Caster\PdoCaster::castPdo',
        'PDOStatement' => 'Symfony\Component\VarDumper\Caster\PdoCaster::castPdoStatement',

        'AMQPConnection' => 'Symfony\Component\VarDumper\Caster\AmqpCaster::castConnection',
        'AMQPChannel' => 'Symfony\Component\VarDumper\Caster\AmqpCaster::castChannel',
        'AMQPQueue' => 'Symfony\Component\VarDumper\Caster\AmqpCaster::castQueue',
        'AMQPExchange' => 'Symfony\Component\VarDumper\Caster\AmqpCaster::castExchange',
        'AMQPEnvelope' => 'Symfony\Component\VarDumper\Caster\AmqpCaster::castEnvelope',

        'ArrayObject' => 'Symfony\Component\VarDumper\Caster\SplCaster::castArrayObject',
        'SplDoublyLinkedList' => 'Symfony\Component\VarDumper\Caster\SplCaster::castDoublyLinkedList',
        'SplFixedArray' => 'Symfony\Component\VarDumper\Caster\SplCaster::castFixedArray',
        'SplHeap' => 'Symfony\Component\VarDumper\Caster\SplCaster::castHeap',
        'SplObjectStorage' => 'Symfony\Component\VarDumper\Caster\SplCaster::castObjectStorage',
        'SplPriorityQueue' => 'Symfony\Component\VarDumper\Caster\SplCaster::castHeap',

        'MongoCursorInterface' => 'Symfony\Component\VarDumper\Caster\MongoCaster::castCursor',

        ':curl' => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castCurl',
        ':dba' => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castDba',
        ':dba persistent' => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castDba',
        ':gd' => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castGd',
        ':mysql link' => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castMysqlLink',
        ':process' => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castProcess',
        ':stream' => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castStream',
        ':stream-context' => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castStreamContext',
        ':xml' => 'Symfony\Component\VarDumper\Caster\XmlResourceCaster::castXml',
    );

    protected $maxItems = 2500;
    protected $maxString = -1;
    protected $useExt;

    private $casters = array();
    private $prevErrorHandler;
    private $classInfo = array();
    private $filter = 0;

    /**
     * @param callable[]|null $casters A map of casters.
     *
     * @see addCasters
     */
    public function __construct(array $casters = null)
    {
        if (null === $casters) {
            $casters = static::$defaultCasters;
        }
        $this->addCasters($casters);
        $this->useExt = extension_loaded('symfony_debug');
    }

    /**
     * Adds casters for resources and objects.
     *
     * Maps resources or objects types to a callback.
     * Types are in the key, with a callable caster for value.
     * Resource types are to be prefixed with a `:`,
     * see e.g. static::$defaultCasters.
     *
     * @param callable[] $casters A map of casters.
     */
    public function addCasters(array $casters)
    {
        foreach ($casters as $type => $callback) {
            $this->casters[strtolower($type)][] = $callback;
        }
    }

    /**
     * Sets the maximum number of items to clone past the first level in nested structures.
     *
     * @param int $maxItems
     */
    public function setMaxItems($maxItems)
    {
        $this->maxItems = (int) $maxItems;
    }

    /**
     * Sets the maximum cloned length for strings.
     *
     * @param int $maxString
     */
    public function setMaxString($maxString)
    {
        $this->maxString = (int) $maxString;
    }

    /**
     * Clones a PHP variable.
     *
     * @param mixed $var    Any PHP variable.
     * @param int   $filter A bit field of Caster::EXCLUDE_* constants.
     *
     * @return Data The cloned variable represented by a Data object.
     */
    public function cloneVar($var, $filter = 0)
    {
        $this->prevErrorHandler = set_error_handler(function($type, $msg, $file, $line, $context) {
            if (E_RECOVERABLE_ERROR === $type || E_USER_ERROR === $type) {
                // Cloner never dies
                throw new \ErrorException($msg, 0, $type, $file, $line);
            }

            if ($this->prevErrorHandler) {
                return call_user_func($this->prevErrorHandler, $type, $msg, $file, $line, $context);
            }

            return false;
        });
        $this->filter = $filter;

        try {
            if (!function_exists('iconv')) {
                $this->maxString = -1;
            }
            $data = $this->doClone($var);
        } catch (\Exception $e) {
        }
        restore_error_handler();
        $this->prevErrorHandler = null;

        if (isset($e)) {
            throw $e;
        }

        return new Data($data);
    }

    /**
     * Effectively clones the PHP variable.
     *
     * @param mixed $var Any PHP variable.
     *
     * @return array The cloned variable represented in an array.
     */
    abstract protected function doClone($var);

    /**
     * Casts an object to an array representation.
     *
     * @param Stub $stub     The Stub for the casted object.
     * @param bool $isNested True if the object is nested in the dumped structure.
     *
     * @return array The object casted as array.
     */
    protected function castObject(Stub $stub, $isNested)
    {
        $obj = $stub->value;
        $class = $stub->class;

        if (isset($this->classInfo[$class])) {
            $classInfo = $this->classInfo[$class];
            $stub->class = $classInfo[0];
        } else {
            $classInfo = array(
                $class,
                new \ReflectionClass($class),
                array_reverse(array('*' => '*', $class => $class) + class_parents($class) + class_implements($class)),
            );

            $this->classInfo[$class] = $classInfo;
        }

        $a = $this->callCaster('Symfony\Component\VarDumper\Caster\Caster::castObject', $obj, $classInfo[1], null, $isNested);

        foreach ($classInfo[2] as $p) {
            if (!empty($this->casters[$p = strtolower($p)])) {
                foreach ($this->casters[$p] as $p) {
                    $a = $this->callCaster($p, $obj, $a, $stub, $isNested);
                }
            }
        }

        return $a;
    }

    /**
     * Casts a resource to an array representation.
     *
     * @param Stub $stub     The Stub for the casted resource.
     * @param bool $isNested True if the object is nested in the dumped structure.
     *
     * @return array The resource casted as array.
     */
    protected function castResource(Stub $stub, $isNested)
    {
        $a = array();
        $res = $stub->value;
        $type = $stub->class;

        if (!empty($this->casters[':'.$type])) {
            foreach ($this->casters[':'.$type] as $c) {
                $a = $this->callCaster($c, $res, $a, $stub, $isNested);
            }
        }

        return $a;
    }

    /**
     * Calls a custom caster.
     *
     * @param callable        $callback The caster.
     * @param object|resource $obj      The object/resource being casted.
     * @param array           $a        The result of the previous cast for chained casters.
     * @param Stub            $stub     The Stub for the casted object/resource.
     * @param bool            $isNested True if $obj is nested in the dumped structure.
     *
     * @return array The casted object/resource.
     */
    private function callCaster($callback, $obj, $a, $stub, $isNested)
    {
        try {
            $cast = call_user_func($callback, $obj, $a, $stub, $isNested, $this->filter);

            if (is_array($cast)) {
                $a = $cast;
            }
        } catch (\Exception $e) {
            $a[(Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '').'⚠'] = new ThrowingCasterException($callback, $e);
        }

        return $a;
    }
}
