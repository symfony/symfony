<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Jeroen Spee <https://github.com/Jeroeny>
 * @author Vojtech Smejkal <https://vojtechsmejkal.cz>
 *
 * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages
 */
class FirebaseOptions implements MessageOptionsInterface
{
    /**
     * @param array<string, string> $data arbitrary meta-data (both keys and values must be a string)
     */
    public function __construct(
        private string $target,
        protected array $options = [],
        array $data = [],
        protected TargetType $targetType = TargetType::Topic,
    ) {
        $this->options['data'] = $data;
    }

    public function toArray(): array
    {
        $options = $this->options;
        $options[$this->targetType->value] = $this->target;

        return $options;
    }

    public function getRecipientId(): ?string
    {
        return '['.$this->targetType->value.']'.$this->target;
    }

    /**
     * @return $this
     */
    public function title(string $title): static
    {
        $this->addNotificationOption('title', $title);

        return $this;
    }

    /**
     * @return $this
     */
    public function body(string $body): static
    {
        $this->addNotificationOption('body', $body);

        return $this;
    }

    /**
     * @param string $image URL of an image that is going to be downloaded on the device and displayed in a notification
     *
     * @return $this
     */
    public function image(string $image): static
    {
        $this->addNotificationOption('image', $image);

        return $this;
    }

    /**
     * @param array<string, string> $data
     *
     * @return $this
     */
    public function data(array $data): static
    {
        $this->options['data'] = $data;

        return $this;
    }

    /**
     * @param array<string, mixed> $notification
     *
     * @return $this
     *
     * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#notification
     */
    public function notification(array $notification): static
    {
        $this->options['notification'] = $notification;

        return $this;
    }

    /**
     * @param array<string, mixed> $android
     *
     * @return $this
     *
     * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#androidconfig
     */
    public function android(array $android): static
    {
        $this->options['android'] = $android;

        return $this;
    }

    /**
     * @param array<string, mixed> $webpush
     *
     * @return $this
     *
     * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#webpushconfig
     */
    public function webpush(array $webpush): static
    {
        $this->options['webpush'] = $webpush;

        return $this;
    }

    /**
     * @param array<string, mixed> $apns
     *
     * @return $this
     *
     * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#apnsconfig
     */
    public function apns(array $apns): static
    {
        $this->options['apns'] = $apns;

        return $this;
    }

    /**
     * @param array<string, mixed> $fcmOptions
     *
     * @return $this
     *
     * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#fcmoptions
     */
    public function fcmOptions(array $fcmOptions): static
    {
        $this->options['fcm_options'] = $fcmOptions;

        return $this;
    }

    /**
     * @return $this
     */
    protected function addNotificationOption(string $key, mixed $value): static
    {
        $this->options['notification'] ??= [];
        $this->options['notification'][$key] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    protected function addAndroidOption(string $key, mixed $value): static
    {
        $this->options['android'] ??= [];
        $this->options['android']['notification'] ??= [];
        $this->options['android']['notification'][$key] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    protected function addWebpushOption(string $key, mixed $value): static
    {
        $this->options['webpush'] ??= [];
        $this->options['webpush']['notification'] ??= [];
        $this->options['webpush']['notification'][$key] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    protected function addApnsOption(string $key, mixed $value): static
    {
        $this->options['apns'] ??= [];
        $this->options['apns']['payload'] ??= [];
        $this->options['apns']['payload']['aps'] ??= [];
        $this->options['apns']['payload']['aps'][$key] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    protected function addApnsAlertOption(string $key, mixed $value): static
    {
        $this->options['apns'] ??= [];
        $this->options['apns']['payload'] ??= [];
        $this->options['apns']['payload']['aps'] ??= [];
        $this->options['apns']['payload']['aps']['alert'] ??= [];
        $this->options['apns']['payload']['aps']['alert'][$key] = $value;

        return $this;
    }
}
