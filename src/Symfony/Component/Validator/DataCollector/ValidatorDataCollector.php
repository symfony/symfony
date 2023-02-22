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
use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @final
 */
class ValidatorDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private TraceableValidator $validator;

    public function __construct(TraceableValidator $validator)
    {
        $this->validator = $validator;
        $this->reset();
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        // Everything is collected once, on kernel terminate.
    }

    public function reset(): void
    {
        $this->data = [
            'calls' => $this->cloneVar([]),
            'violations_count' => 0,
        ];
    }

    public function lateCollect(): void
    {
        $collected = $this->validator->getCollectedData();
        $this->data['calls'] = $this->cloneVar($collected);
        $this->data['violations_count'] = array_reduce($collected, fn ($previous, $item) => $previous + \count($item['violations']), 0);
    }

    public function getCalls(): Data
    {
        return $this->data['calls'];
    }

    public function getViolationsCount(): int
    {
        return $this->data['violations_count'];
    }

    public function getName(): string
    {
        return 'validator';
    }

    protected function getCasters(): array
    {
        return parent::getCasters() + [
            \Exception::class => function (\Exception $e, array $a, Stub $s) {
                foreach (["\0Exception\0previous", "\0Exception\0trace"] as $k) {
                    if (isset($a[$k])) {
                        unset($a[$k]);
                        ++$s->cut;
                    }
                }

                return $a;
            },
            FormInterface::class => fn (FormInterface $f, array $a) => [
                Caster::PREFIX_VIRTUAL.'name' => $f->getName(),
                Caster::PREFIX_VIRTUAL.'type_class' => new ClassStub($f->getConfig()->getType()->getInnerType()::class),
                Caster::PREFIX_VIRTUAL.'data' => $f->getData(),
            ],
        ];
    }
}
