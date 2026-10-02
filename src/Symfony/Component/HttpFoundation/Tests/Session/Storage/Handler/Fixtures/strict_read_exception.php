<?php

require __DIR__.'/common.inc';

use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;

class ThrowingSessionHandler implements \SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        echo __FUNCTION__, "\n";

        return true;
    }

    public function close(): bool
    {
        echo __FUNCTION__, "\n";

        return true;
    }

    public function read(string $id): string|false
    {
        echo __FUNCTION__, "\n";

        throw new \RuntimeException('storage unreachable');
    }

    public function write(string $id, string $data): bool
    {
        echo __FUNCTION__, "\n";

        return true;
    }

    public function destroy(string $id): bool
    {
        echo __FUNCTION__, "\n";

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        echo __FUNCTION__, "\n";

        return 0;
    }

    public function create_sid(): string
    {
        return session_create_id();
    }

    public function validateId(string $id): bool
    {
        return true;
    }
}

set_exception_handler(function (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
});

session_set_save_handler(new StrictSessionHandler(new ThrowingSessionHandler()), false);
session_start();
echo "not reached\n";
