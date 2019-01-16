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
    private $targetDirs = [];

    public function __construct()
    {
        $this->services = [];
        $this->normalizedIds = [
            'tsantos\\serializer\\serializerinterface' => 'TSantos\\Serializer\\SerializerInterface',
        ];
        $this->methodMap = [
            'tsantos_serializer' => 'getTsantosSerializerService',
        ];
        $this->aliases = [
            'TSantos\\Serializer\\SerializerInterface' => 'tsantos_serializer',
        ];
    }

    public function getRemovedIds()
    {
        return [
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
        ];
    }

    public function compile()
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled()
    {
        return true;
    }

    public function isFrozen()
    {
        @trigger_error(sprintf('The %s() method is deprecated since Symfony 3.3 and will be removed in 4.0. Use the isCompiled() method instead.', __METHOD__), E_USER_DEPRECATED);

        return true;
    }

    /**
     * Gets the public 'tsantos_serializer' shared service.
     *
     * @return \TSantos\Serializer\EventEmitterSerializer
     */
    protected function getTsantosSerializerService()
    {
        $a = new \TSantos\Serializer\NormalizerRegistry();

        $b = new \TSantos\Serializer\Normalizer\CollectionNormalizer();

        $c = new \TSantos\Serializer\EventDispatcher\EventDispatcher();
        $c->addSubscriber(new \TSantos\SerializerBundle\EventListener\StopwatchListener(new \Symfony\Component\Stopwatch\Stopwatch(true)));

        $this->services['tsantos_serializer'] = $instance = new \TSantos\Serializer\EventEmitterSerializer(new \TSantos\Serializer\Encoder\JsonEncoder(), $a, $c);

        $b->setSerializer($instance);
        $d = new \TSantos\Serializer\Normalizer\JsonNormalizer();
        $d->setSerializer($instance);

        $a->add(new \TSantos\Serializer\Normalizer\ObjectNormalizer(new \TSantos\SerializerBundle\Serializer\CircularReferenceHandler()));
        $a->add($b);
        $a->add($d);

        return $instance;
    }
}
