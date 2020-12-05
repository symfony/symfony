<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Serializer\Debug\Normalizer\AbstractTraceableNormalizer;
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

class SerializerDataCollector extends DataCollector
{
    /**
     * @var SerializerInterface|null
     */
    private $serializer;
    /**
     * @var array|iterable
     */
    private $normalizers;

    public function __construct(SerializerInterface $serializer = null, iterable $normalizers = [])
    {
        $this->serializer = $serializer;
        $this->normalizers = $normalizers;
    }

    public function collect(Request $request, Response $response, Throwable $exception = null): void
    {
        $this->initData();

        $this->fillWithSerializerData();

        $this->fillWithNormalizerData();

        $this->finalizeData();
    }

    public function getName(): string
    {
        return 'serializer.data_collector';
    }

    public function reset(): void
    {
        $this->data = [];

        if ($this->serializer instanceof ResetInterface) {
            $this->serializer->reset();
        }
    }

    public function getSerializations(): Data
    {
        return $this->data['serializations'];
    }

    public function getDeserializations(): Data
    {
        return $this->data['deserializations'];
    }

    public function getNormalizations(): Data
    {
        return $this->data['normalizations'];
    }

    public function getDenormalizations(): Data
    {
        return $this->data['denormalizations'];
    }

    private function initData(): void
    {
        $this->data = [
            'serializations' => [],
            'deserializations' => [],
            'normalizations' => [],
            'denormalizations' => [],
        ];
    }

    private function fillWithSerializerData(): void
    {
        if ($this->serializer instanceof TraceableSerializer) {
            $this->data['serializations'] = $this->serializer->getSerializations();
            $this->data['deserializations'] = $this->serializer->getDeserializations();
        }
    }

    private function fillWithNormalizerData(): void
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof AbstractTraceableNormalizer) {
                foreach ($normalizer->getNormalizations() as $denormalization) {
                    $this->data['normalizations'][] = $denormalization;
                }
                foreach ($normalizer->getDenormalizations() as $denormalization) {
                    $this->data['denormalizations'][] = $denormalization;
                }
            }
        }
    }

    private function finalizeData(): void
    {
        $this->data = $this->cloneVar($this->data);
    }
}
