<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Serializer;

use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Scheduler\Task\CallBackTask;
use Symfony\Component\Scheduler\Task\ChainedTask;
use Symfony\Component\Scheduler\Task\CommandTask;
use Symfony\Component\Scheduler\Task\HttpTask;
use Symfony\Component\Scheduler\Task\MessengerTask;
use Symfony\Component\Scheduler\Task\NotificationTask;
use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\SingleRunTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskNormalizer extends ObjectNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = [])
    {
        $data = parent::normalize($object, $format, $context);

        $data['internal_type'] = \get_class($object);

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $objectType = $data['internal_type'];

        if (CallBackTask::class === $objectType) {
            return parent::denormalize($data, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    $objectType => ['name' => $data['name'], 'callback' => $data['options']['callback'], 'arguments' => $data['options']['arguments'], 'options' => $data['options']]
                ]
            ]);
        }

        if (ChainedTask::class === $objectType) {
            $finalTasks = [];
            foreach ($data['options']['tasks'] as $task) {
                $finalTasks[] = $this->denormalize($task, $task['options']['internal_type'], $format, $context);
            }

            return parent::denormalize($data, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    $objectType => ['name' => $data['name'], 'tasks' => $finalTasks, 'options' => $data['options']]
                ]
            ]);
        }

        if (CommandTask::class === $objectType) {
            return parent::denormalize($data, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    $objectType => ['name' => $data['name'], 'command' => $data['options']['command'], 'options' => $data['options']]
                ]
            ]);
        }

        if (SingleRunTask::class === $objectType) {
            $taskToDenormalize = $data['options']['task'];
            $task = $this->denormalize($taskToDenormalize, $taskToDenormalize['internal_type'], $format, $context);

            return parent::denormalize($data, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    $objectType => ['name' => $data['name'], 'task' => $task, 'options' => $data['options']]
                ]
            ]);
        }

        if (NullTask::class === $objectType) {
            return parent::denormalize($data, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    $objectType => ['name' => $data['name'], 'options' => $data['options']]
                ]
            ]);
        }

        if (ShellTask::class === $objectType) {
            return parent::denormalize($data, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    $objectType => ['name' => $data['name'], 'command' => $data['options']['command'], 'options' => $data['options']]
                ]
            ]);
        }

        if (MessengerTask::class === $objectType) {
            return parent::denormalize($data, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    $objectType => ['name' => $data['name'], 'message' => $data['options']['message'], 'options' => $data['options']]
                ]
            ]);
        }

        if (NotificationTask::class === $objectType) {
            $notification = parent::denormalize($data['options']['notification'], Notification::class, 'json');
            $recipient = parent::denormalize($data['options']['notification'], Recipient::class, 'json');

            return parent::denormalize($data, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    $objectType => ['name' => $data['name'], 'notification' => $notification, 'recipient' => $recipient, 'options' => $data['options']]
                ]
            ]);
        }

        if (HttpTask::class === $objectType) {
            return parent::denormalize($data, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    $objectType => ['name' => $data['name'], 'url' => $data['options']['url'], 'client_options' => $data['options']['client_options'], 'options' => $data['options']]
                ]
            ]);
        }

        return parent::denormalize($data, $objectType, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof TaskInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null)
    {
        return \array_key_exists('internal_type', $data);
    }
}
