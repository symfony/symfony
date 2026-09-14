<?php

use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Command\DecryptCommand;
use Symfony\Component\KeyManagement\Command\EncryptCommand;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$key = base64_decode((string) getenv('KMS_TEST_KEY'), true);
if (32 !== \strlen((string) $key)) {
    fwrite(\STDERR, 'The KMS_TEST_KEY environment variable must hold a base64-encoded 32-byte key.'.\PHP_EOL);

    exit(1);
}

$kms = new OpenSslKms(new InMemoryKeyLoader(['app' => $key]));
$clients = new ServiceLocator(['default' => static fn (): OpenSslKms => $kms]);

$application = new Application();
$application->addCommand(new EncryptCommand($clients));
$application->addCommand(new DecryptCommand($clients));
$application->run();
