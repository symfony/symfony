<?php

namespace Symfony\Bundle\FrameworkBundle\Secret;

interface SecretStorageInterface
{
    public function getSecret(string $key): string;

    public function putSecret(string $key, string $secret): void;

    public function deleteSecret(string $key): void;

    public function listSecrets(): iterable;
}
