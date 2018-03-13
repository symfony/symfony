<?php

$componentRoot = $_SERVER['COMPONENT_ROOT'];

if (!is_file($autoload = $componentRoot.'/vendor/autoload.php')) {
    $autoload = $componentRoot.'/../../../../vendor/autoload.php';
}

if (!file_exists($autoload)) {
    exit('You should run "composer install --dev" in the component before running this script.');
}

require_once $autoload;

use Symfony\Component\Messenger\Adapter\AmqpExt\AmqpReceiver;
use Symfony\Component\Messenger\Adapter\AmqpExt\AmqpSender;
use Symfony\Component\Messenger\Adapter\AmqpExt\Connection;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

$serializer = new Serializer(
    new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
);

$connection = Connection::fromDsn(getenv('DSN'));
$sender = new AmqpSender($serializer, $connection);
$receiver = new AmqpReceiver($serializer, $connection);

$worker = new Worker($receiver, new class() implements MessageBusInterface {
    public function dispatch($message)
    {
        echo 'Get message: '.get_class($message)."\n";
        sleep(30);
        echo "Done.\n";
    }
});

echo "Receiving messages...\n";
$worker->run();
