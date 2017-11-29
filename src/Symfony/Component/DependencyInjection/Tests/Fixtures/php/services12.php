<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class Symfony_DI_PhpDumper_Test_Relative_Dir extends Container
{
    const SAME_DIR = __DIR__ === '%path%/src/Symfony/Component/DependencyInjection/Tests/Fixtures';
    const TARGET_DIR_1 = '%path%/src/Symfony/Component/DependencyInjection/Tests';
    const TARGET_DIR_2 = '%path%/src/Symfony/Component/DependencyInjection';
    const TARGET_DIR_3 = '%path%/src/Symfony/Component';
    const TARGET_DIR_4 = '%path%/src/Symfony';
    const TARGET_DIR_5 = '%path%/src';

    private $parameters;
    private $targetDirs = array();
    protected $methodMap = array(
        'test' => 'getTestService',
    );

    protected $aliases = array();

    public function __construct()
    {
        if (!self::SAME_DIR) {
            $this->targetDirs = array();
            $dir = __DIR__;
            for ($i = 1; $i <= 5; ++$i) {
                $this->targetDirs[$i] = $dir = dirname($dir);
            }
        }
        $this->parameters = array(
            'foo' => ('wiz'.(self::SAME_DIR ? self::TARGET_DIR_1 : $this->targetDirs[1])),
            'bar' => __DIR__,
            'baz' => (__DIR__.'/PhpDumperTest.php'),
            'buz' => (self::SAME_DIR ? self::TARGET_DIR_2 : $this->targetDirs[2]),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        throw new LogicException('You cannot compile a dumped frozen container.');
    }

    /**
     * {@inheritdoc}
     */
    public function isFrozen()
    {
        return true;
    }

    /**
     * Gets the public 'test' shared service.
     *
     * @return \stdClass
     */
    protected function getTestService()
    {
        return $this->services['test'] = new \stdClass(('wiz'.(self::SAME_DIR ? self::TARGET_DIR_1 : $this->targetDirs[1])), array(('wiz'.(self::SAME_DIR ? self::TARGET_DIR_1 : $this->targetDirs[1])) => ((self::SAME_DIR ? self::TARGET_DIR_2 : $this->targetDirs[2]).'/')));
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter($name)
    {
        $name = strtolower($name);

        if (!(isset($this->parameters[$name]) || array_key_exists($name, $this->parameters))) {
            throw new InvalidArgumentException(sprintf('The parameter "%s" must be defined.', $name));
        }

        return $this->parameters[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameter($name)
    {
        $name = strtolower($name);

        return isset($this->parameters[$name]) || array_key_exists($name, $this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter($name, $value)
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterBag()
    {
        if (null === $this->parameterBag) {
            $this->parameterBag = new FrozenParameterBag($this->parameters);
        }

        return $this->parameterBag;
    }
}
