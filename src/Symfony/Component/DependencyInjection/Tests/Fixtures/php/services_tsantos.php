<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class ProjectServiceContainer extends Container
{
    private $parameters;
    private $targetDirs = array();

    /**
     * @internal but protected for BC on cache:clear
     */
    protected $privates = array();

    public function __construct()
    {
        $this->services = $this->privates = array();
        $this->methodMap = array(
            'tsantos_serializer' => 'getTsantosSerializerService',
        );
        $this->aliases = array(
            'TSantos\\Serializer\\SerializerInterface' => 'tsantos_serializer',
        );
    }

    public function reset()
    {
        $this->privates = array();
        parent::reset();
    }

    public function compile()
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled()
    {
        return true;
    }

    public function getRemovedIds()
    {
        return array(
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
        );
    }

    /**
     * Gets the public 'tsantos_serializer' shared service.
     *
     * @return \TSantos\Serializer\EventEmitterSerializer
     */
    protected function getTsantosSerializerService()
    {
        $a = new \TSantos\Serializer\NormalizerRegistry();

        $d = new \TSantos\Serializer\EventDispatcher\EventDispatcher();
        $d->addSubscriber(new \TSantos\SerializerBundle\EventListener\StopwatchListener(new \Symfony\Component\Stopwatch\Stopwatch(true)));

        $this->services['tsantos_serializer'] = $instance = new \TSantos\Serializer\EventEmitterSerializer(new \TSantos\Serializer\Encoder\JsonEncoder(), $a, $d);

        $b = new \TSantos\Serializer\Normalizer\CollectionNormalizer();

        $b->setSerializer($instance);

        $c = new \TSantos\Serializer\Normalizer\JsonNormalizer();

        $c->setSerializer($instance);

        $a->add(new \TSantos\Serializer\Normalizer\ObjectNormalizer(new \TSantos\SerializerBundle\Serializer\CircularReferenceHandler()));
        $a->add($b);
        $a->add($c);

        return $instance;
    }
}
