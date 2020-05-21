<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Kubernetes\Serializer;

use Symfony\Component\Scheduler\Bridge\Kubernetes\Task\CronJob;
use Symfony\Component\Scheduler\Serializer\TaskNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronJobNormalizer implements DenormalizerInterface, NormalizerInterface
{
    private $taskNormalizer;

    public function __construct(TaskNormalizer $taskNormalizer)
    {
        $this->taskNormalizer = $taskNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = [])
    {
        $data = $this->taskNormalizer->normalize($object, $format, $context);

        $data['kind'] = 'CronJob';
        $data['metadata'] = ['labels' => [
            'name' => $data['options']['name'],
            'internal_type' => CronJob::class,
            'annotations' => $data['options'],
        ]];
        $data['spec'] = $data['options']['spec'];
        $data['status'] = $data['options']['job_status'];

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        return $this->taskNormalizer->denormalize($data, $type, $format, [
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                CronJob::class => [
                    'name' => $data['name'],
                    'apiVersion' => $data['api_version'],
                    'spec' => $data['spec'],
                    'jobStatus' => $data['job_status'],
                    'options' => $data['metadata']['annotations']
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof CronJob;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null)
    {
        return \array_key_exists('internal_type', $data['metadata']['labels']) && $data['metadata']['labels']['internal_type'] === CronJob::class;
    }
}
