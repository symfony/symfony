<?php

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Terminal;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor/autoload.php')) {
    if ($vendor === \dirname($vendor)) {
        throw new RuntimeException('Cannot find the Console test autoloader.');
    }
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

// The parent configures the PTY before releasing the child.
fgets(fopen('php://fd/4', 'r'));
putenv('COLUMNS');
putenv('LINES');
putenv('ANSICON');
(new ReflectionProperty(Terminal::class, 'stty'))->setValue(null, false);

if ('dimensions' === $argv[1] || 'compatibility' === $argv[1]) {
    $terminal = new Terminal();
    echo json_encode([$terminal->getWidth(), $terminal->getHeight()]);

    return;
}

$input = new ArrayInput([]);
$input->setStream(fopen('disabled' === $argv[1] ? 'php://stdin' : 'php://fd/3', 'r'));
$output = new BufferedOutput();
$question = new Question('Password: ');
$question->setHidden(true);
$question->setTrimmable('untrimmed' !== $argv[1]);
$question->setHiddenFallback('fallback' === $argv[1]);
if ('disabled' === $argv[1]) {
    QuestionHelper::disableStty();
}

echo "READY\n";
try {
    $value = (new QuestionHelper())->ask($input, $output, $question);
    echo json_encode(['value' => $value, 'output' => $output->fetch()]);
} catch (Throwable $e) {
    echo json_encode(['exception' => $e::class, 'previous' => $e->getPrevious() ? $e->getPrevious()::class : null, 'output' => $output->fetch()]);
}
