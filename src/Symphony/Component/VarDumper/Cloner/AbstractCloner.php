<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\VarDumper\Cloner;

use Symphony\Component\VarDumper\Caster\Caster;
use Symphony\Component\VarDumper\Exception\ThrowingCasterException;

/**
 * AbstractCloner implements a generic caster mechanism for objects and resources.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractCloner implements ClonerInterface
{
    public static $defaultCasters = array(
        '__PHP_Incomplete_Class' => array('Symphony\Component\VarDumper\Caster\Caster', 'castPhpIncompleteClass'),

        'Symphony\Component\VarDumper\Caster\CutStub' => array('Symphony\Component\VarDumper\Caster\StubCaster', 'castStub'),
        'Symphony\Component\VarDumper\Caster\CutArrayStub' => array('Symphony\Component\VarDumper\Caster\StubCaster', 'castCutArray'),
        'Symphony\Component\VarDumper\Caster\ConstStub' => array('Symphony\Component\VarDumper\Caster\StubCaster', 'castStub'),
        'Symphony\Component\VarDumper\Caster\EnumStub' => array('Symphony\Component\VarDumper\Caster\StubCaster', 'castEnum'),

        'Closure' => array('Symphony\Component\VarDumper\Caster\ReflectionCaster', 'castClosure'),
        'Generator' => array('Symphony\Component\VarDumper\Caster\ReflectionCaster', 'castGenerator'),
        'ReflectionType' => array('Symphony\Component\VarDumper\Caster\ReflectionCaster', 'castType'),
        'ReflectionGenerator' => array('Symphony\Component\VarDumper\Caster\ReflectionCaster', 'castReflectionGenerator'),
        'ReflectionClass' => array('Symphony\Component\VarDumper\Caster\ReflectionCaster', 'castClass'),
        'ReflectionFunctionAbstract' => array('Symphony\Component\VarDumper\Caster\ReflectionCaster', 'castFunctionAbstract'),
        'ReflectionMethod' => array('Symphony\Component\VarDumper\Caster\ReflectionCaster', 'castMethod'),
        'ReflectionParameter' => array('Symphony\Component\VarDumper\Caster\ReflectionCaster', 'castParameter'),
        'ReflectionProperty' => array('Symphony\Component\VarDumper\Caster\ReflectionCaster', 'castProperty'),
        'ReflectionExtension' => array('Symphony\Component\VarDumper\Caster\ReflectionCaster', 'castExtension'),
        'ReflectionZendExtension' => array('Symphony\Component\VarDumper\Caster\ReflectionCaster', 'castZendExtension'),

        'Doctrine\Common\Persistence\ObjectManager' => array('Symphony\Component\VarDumper\Caster\StubCaster', 'cutInternals'),
        'Doctrine\Common\Proxy\Proxy' => array('Symphony\Component\VarDumper\Caster\DoctrineCaster', 'castCommonProxy'),
        'Doctrine\ORM\Proxy\Proxy' => array('Symphony\Component\VarDumper\Caster\DoctrineCaster', 'castOrmProxy'),
        'Doctrine\ORM\PersistentCollection' => array('Symphony\Component\VarDumper\Caster\DoctrineCaster', 'castPersistentCollection'),

        'DOMException' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castException'),
        'DOMStringList' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castLength'),
        'DOMNameList' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castLength'),
        'DOMImplementation' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castImplementation'),
        'DOMImplementationList' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castLength'),
        'DOMNode' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castNode'),
        'DOMNameSpaceNode' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castNameSpaceNode'),
        'DOMDocument' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castDocument'),
        'DOMNodeList' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castLength'),
        'DOMNamedNodeMap' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castLength'),
        'DOMCharacterData' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castCharacterData'),
        'DOMAttr' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castAttr'),
        'DOMElement' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castElement'),
        'DOMText' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castText'),
        'DOMTypeinfo' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castTypeinfo'),
        'DOMDomError' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castDomError'),
        'DOMLocator' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castLocator'),
        'DOMDocumentType' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castDocumentType'),
        'DOMNotation' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castNotation'),
        'DOMEntity' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castEntity'),
        'DOMProcessingInstruction' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castProcessingInstruction'),
        'DOMXPath' => array('Symphony\Component\VarDumper\Caster\DOMCaster', 'castXPath'),

        'XmlReader' => array('Symphony\Component\VarDumper\Caster\XmlReaderCaster', 'castXmlReader'),

        'ErrorException' => array('Symphony\Component\VarDumper\Caster\ExceptionCaster', 'castErrorException'),
        'Exception' => array('Symphony\Component\VarDumper\Caster\ExceptionCaster', 'castException'),
        'Error' => array('Symphony\Component\VarDumper\Caster\ExceptionCaster', 'castError'),
        'Symphony\Component\DependencyInjection\ContainerInterface' => array('Symphony\Component\VarDumper\Caster\StubCaster', 'cutInternals'),
        'Symphony\Component\HttpFoundation\Request' => array('Symphony\Component\VarDumper\Caster\SymphonyCaster', 'castRequest'),
        'Symphony\Component\VarDumper\Exception\ThrowingCasterException' => array('Symphony\Component\VarDumper\Caster\ExceptionCaster', 'castThrowingCasterException'),
        'Symphony\Component\VarDumper\Caster\TraceStub' => array('Symphony\Component\VarDumper\Caster\ExceptionCaster', 'castTraceStub'),
        'Symphony\Component\VarDumper\Caster\FrameStub' => array('Symphony\Component\VarDumper\Caster\ExceptionCaster', 'castFrameStub'),
        'Symphony\Component\Debug\Exception\SilencedErrorContext' => array('Symphony\Component\VarDumper\Caster\ExceptionCaster', 'castSilencedErrorContext'),

        'PHPUnit_Framework_MockObject_MockObject' => array('Symphony\Component\VarDumper\Caster\StubCaster', 'cutInternals'),
        'Prophecy\Prophecy\ProphecySubjectInterface' => array('Symphony\Component\VarDumper\Caster\StubCaster', 'cutInternals'),
        'Mockery\MockInterface' => array('Symphony\Component\VarDumper\Caster\StubCaster', 'cutInternals'),

        'PDO' => array('Symphony\Component\VarDumper\Caster\PdoCaster', 'castPdo'),
        'PDOStatement' => array('Symphony\Component\VarDumper\Caster\PdoCaster', 'castPdoStatement'),

        'AMQPConnection' => array('Symphony\Component\VarDumper\Caster\AmqpCaster', 'castConnection'),
        'AMQPChannel' => array('Symphony\Component\VarDumper\Caster\AmqpCaster', 'castChannel'),
        'AMQPQueue' => array('Symphony\Component\VarDumper\Caster\AmqpCaster', 'castQueue'),
        'AMQPExchange' => array('Symphony\Component\VarDumper\Caster\AmqpCaster', 'castExchange'),
        'AMQPEnvelope' => array('Symphony\Component\VarDumper\Caster\AmqpCaster', 'castEnvelope'),

        'ArrayObject' => array('Symphony\Component\VarDumper\Caster\SplCaster', 'castArrayObject'),
        'SplDoublyLinkedList' => array('Symphony\Component\VarDumper\Caster\SplCaster', 'castDoublyLinkedList'),
        'SplFileInfo' => array('Symphony\Component\VarDumper\Caster\SplCaster', 'castFileInfo'),
        'SplFileObject' => array('Symphony\Component\VarDumper\Caster\SplCaster', 'castFileObject'),
        'SplFixedArray' => array('Symphony\Component\VarDumper\Caster\SplCaster', 'castFixedArray'),
        'SplHeap' => array('Symphony\Component\VarDumper\Caster\SplCaster', 'castHeap'),
        'SplObjectStorage' => array('Symphony\Component\VarDumper\Caster\SplCaster', 'castObjectStorage'),
        'SplPriorityQueue' => array('Symphony\Component\VarDumper\Caster\SplCaster', 'castHeap'),
        'OuterIterator' => array('Symphony\Component\VarDumper\Caster\SplCaster', 'castOuterIterator'),

        'Redis' => array('Symphony\Component\VarDumper\Caster\RedisCaster', 'castRedis'),
        'RedisArray' => array('Symphony\Component\VarDumper\Caster\RedisCaster', 'castRedisArray'),

        'DateTimeInterface' => array('Symphony\Component\VarDumper\Caster\DateCaster', 'castDateTime'),
        'DateInterval' => array('Symphony\Component\VarDumper\Caster\DateCaster', 'castInterval'),
        'DateTimeZone' => array('Symphony\Component\VarDumper\Caster\DateCaster', 'castTimeZone'),
        'DatePeriod' => array('Symphony\Component\VarDumper\Caster\DateCaster', 'castPeriod'),

        'GMP' => array('Symphony\Component\VarDumper\Caster\GmpCaster', 'castGmp'),

        ':curl' => array('Symphony\Component\VarDumper\Caster\ResourceCaster', 'castCurl'),
        ':dba' => array('Symphony\Component\VarDumper\Caster\ResourceCaster', 'castDba'),
        ':dba persistent' => array('Symphony\Component\VarDumper\Caster\ResourceCaster', 'castDba'),
        ':gd' => array('Symphony\Component\VarDumper\Caster\ResourceCaster', 'castGd'),
        ':mysql link' => array('Symphony\Component\VarDumper\Caster\ResourceCaster', 'castMysqlLink'),
        ':pgsql large object' => array('Symphony\Component\VarDumper\Caster\PgSqlCaster', 'castLargeObject'),
        ':pgsql link' => array('Symphony\Component\VarDumper\Caster\PgSqlCaster', 'castLink'),
        ':pgsql link persistent' => array('Symphony\Component\VarDumper\Caster\PgSqlCaster', 'castLink'),
        ':pgsql result' => array('Symphony\Component\VarDumper\Caster\PgSqlCaster', 'castResult'),
        ':process' => array('Symphony\Component\VarDumper\Caster\ResourceCaster', 'castProcess'),
        ':stream' => array('Symphony\Component\VarDumper\Caster\ResourceCaster', 'castStream'),
        ':persistent stream' => array('Symphony\Component\VarDumper\Caster\ResourceCaster', 'castStream'),
        ':stream-context' => array('Symphony\Component\VarDumper\Caster\ResourceCaster', 'castStreamContext'),
        ':xml' => array('Symphony\Component\VarDumper\Caster\XmlResourceCaster', 'castXml'),
    );

    protected $maxItems = 2500;
    protected $maxString = -1;
    protected $minDepth = 1;

    private $casters = array();
    private $prevErrorHandler;
    private $classInfo = array();
    private $filter = 0;

    /**
     * @param callable[]|null $casters A map of casters
     *
     * @see addCasters
     */
    public function __construct(array $casters = null)
    {
        if (null === $casters) {
            $casters = static::$defaultCasters;
        }
        $this->addCasters($casters);
    }

    /**
     * Adds casters for resources and objects.
     *
     * Maps resources or objects types to a callback.
     * Types are in the key, with a callable caster for value.
     * Resource types are to be prefixed with a `:`,
     * see e.g. static::$defaultCasters.
     *
     * @param callable[] $casters A map of casters
     */
    public function addCasters(array $casters)
    {
        foreach ($casters as $type => $callback) {
            $this->casters[strtolower($type)][] = is_string($callback) && false !== strpos($callback, '::') ? explode('::', $callback, 2) : $callback;
        }
    }

    /**
     * Sets the maximum number of items to clone past the minimum depth in nested structures.
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
     * Sets the minimum tree depth where we are guaranteed to clone all the items.  After this
     * depth is reached, only setMaxItems items will be cloned.
     *
     * @param int $minDepth
     */
    public function setMinDepth($minDepth)
    {
        $this->minDepth = (int) $minDepth;
    }

    /**
     * Clones a PHP variable.
     *
     * @param mixed $var    Any PHP variable
     * @param int   $filter A bit field of Caster::EXCLUDE_* constants
     *
     * @return Data The cloned variable represented by a Data object
     */
    public function cloneVar($var, $filter = 0)
    {
        $this->prevErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = array()) {
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

        if ($gc = gc_enabled()) {
            gc_disable();
        }
        try {
            return new Data($this->doClone($var));
        } finally {
            if ($gc) {
                gc_enable();
            }
            restore_error_handler();
            $this->prevErrorHandler = null;
        }
    }

    /**
     * Effectively clones the PHP variable.
     *
     * @param mixed $var Any PHP variable
     *
     * @return array The cloned variable represented in an array
     */
    abstract protected function doClone($var);

    /**
     * Casts an object to an array representation.
     *
     * @param Stub $stub     The Stub for the casted object
     * @param bool $isNested True if the object is nested in the dumped structure
     *
     * @return array The object casted as array
     */
    protected function castObject(Stub $stub, $isNested)
    {
        $obj = $stub->value;
        $class = $stub->class;

        if (isset($class[15]) && "\0" === $class[15] && 0 === strpos($class, "class@anonymous\x00")) {
            $stub->class = get_parent_class($class).'@anonymous';
        }
        if (isset($this->classInfo[$class])) {
            list($i, $parents, $hasDebugInfo) = $this->classInfo[$class];
        } else {
            $i = 2;
            $parents = array(strtolower($class));
            $hasDebugInfo = method_exists($class, '__debugInfo');

            foreach (class_parents($class) as $p) {
                $parents[] = strtolower($p);
                ++$i;
            }
            foreach (class_implements($class) as $p) {
                $parents[] = strtolower($p);
                ++$i;
            }
            $parents[] = '*';

            $this->classInfo[$class] = array($i, $parents, $hasDebugInfo);
        }

        $a = Caster::castObject($obj, $class, $hasDebugInfo);

        try {
            while ($i--) {
                if (!empty($this->casters[$p = $parents[$i]])) {
                    foreach ($this->casters[$p] as $callback) {
                        $a = $callback($obj, $a, $stub, $isNested, $this->filter);
                    }
                }
            }
        } catch (\Exception $e) {
            $a = array((Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '').'⚠' => new ThrowingCasterException($e)) + $a;
        }

        return $a;
    }

    /**
     * Casts a resource to an array representation.
     *
     * @param Stub $stub     The Stub for the casted resource
     * @param bool $isNested True if the object is nested in the dumped structure
     *
     * @return array The resource casted as array
     */
    protected function castResource(Stub $stub, $isNested)
    {
        $a = array();
        $res = $stub->value;
        $type = $stub->class;

        try {
            if (!empty($this->casters[':'.$type])) {
                foreach ($this->casters[':'.$type] as $callback) {
                    $a = $callback($res, $a, $stub, $isNested, $this->filter);
                }
            }
        } catch (\Exception $e) {
            $a = array((Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '').'⚠' => new ThrowingCasterException($e)) + $a;
        }

        return $a;
    }
}
