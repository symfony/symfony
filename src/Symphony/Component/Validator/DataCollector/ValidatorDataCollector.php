<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\DataCollector;

use Symphony\Component\Form\FormInterface;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\DataCollector\DataCollector;
use Symphony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symphony\Component\Validator\Validator\TraceableValidator;
use Symphony\Component\VarDumper\Caster\Caster;
use Symphony\Component\VarDumper\Caster\ClassStub;
use Symphony\Component\VarDumper\Cloner\Data;
use Symphony\Component\VarDumper\Cloner\Stub;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ValidatorDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private $validator;

    public function __construct(TraceableValidator $validator)
    {
        $this->validator = $validator;
        $this->reset();
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // Everything is collected once, on kernel terminate.
    }

    public function reset()
    {
        $this->data = array(
            'calls' => $this->cloneVar(array()),
            'violations_count' => 0,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        $collected = $this->validator->getCollectedData();
        $this->data['calls'] = $this->cloneVar($collected);
        $this->data['violations_count'] = array_reduce($collected, function ($previous, $item) {
            return $previous + count($item['violations']);
        }, 0);
    }

    /**
     * @return Data
     */
    public function getCalls()
    {
        return $this->data['calls'];
    }

    /**
     * @return int
     */
    public function getViolationsCount()
    {
        return $this->data['violations_count'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'validator';
    }

    protected function getCasters()
    {
        return parent::getCasters() + array(
            \Exception::class => function (\Exception $e, array $a, Stub $s) {
                foreach (array("\0Exception\0previous", "\0Exception\0trace") as $k) {
                    if (isset($a[$k])) {
                        unset($a[$k]);
                        ++$s->cut;
                    }
                }

                return $a;
            },
            FormInterface::class => function (FormInterface $f, array $a) {
                return array(
                    Caster::PREFIX_VIRTUAL.'name' => $f->getName(),
                    Caster::PREFIX_VIRTUAL.'type_class' => new ClassStub(get_class($f->getConfig()->getType()->getInnerType())),
                    Caster::PREFIX_VIRTUAL.'data' => $f->getData(),
                );
            },
        );
    }
}
