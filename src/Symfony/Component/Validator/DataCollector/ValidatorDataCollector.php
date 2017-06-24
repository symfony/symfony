<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\DataCollector;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Validator\Validator\TraceableValidator;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ValidatorDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private $validator;
    private $cloner;

    public function __construct(TraceableValidator $validator)
    {
        $this->validator = $validator;
        $this->data = array(
            'calls' => array(),
            'violations_count' => 0,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // Everything is collected once, on kernel terminate.
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        $collected = $this->validator->getCollectedData();
        $this->data['calls'] = $this->cloneVar($collected);
        $this->data['violations_count'] += array_reduce($collected, function ($previous, $item) {
            return $previous += count($item['violations']);
        }, 0);
    }

    public function getCalls()
    {
        return $this->data['calls'];
    }

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

    /**
     * {@inheritdoc}
     */
    protected function cloneVar($var)
    {
        if ($var instanceof Data) {
            return $var;
        }

        if (null === $this->cloner) {
            $this->cloner = new VarCloner();
            $this->cloner->setMaxItems(-1);
            $this->cloner->addCasters(array(
                FormInterface::class => function (FormInterface $f, array $a) {
                    return array(
                        Caster::PREFIX_VIRTUAL.'name' => $f->getName(),
                        Caster::PREFIX_VIRTUAL.'type_class' => new ClassStub(get_class($f->getConfig()->getType()->getInnerType())),
                        Caster::PREFIX_VIRTUAL.'data' => $f->getData(),
                    );
                },
            ));
        }

        return $this->cloner->cloneVar($var, Caster::EXCLUDE_VERBOSE);
    }
}
