<?php

namespace App;

class BarService
{
}

class Db
{
    public Schema $schema;
}

class Bus
{
    public Handler1 $handler1;
    public Handler2 $handler2;

    public function __construct(public Db $db)
    {
    }
}

class Handler1
{
    public function __construct(
        public Db $db,
        public Schema $schema,
        public Processor $processor,
    ) {
    }
}

class Handler2
{
    public function __construct(
        public Db $db,
        public Schema $schema,
        public Processor $processor,
    ) {
    }
}

class Processor
{
    public function __construct(
        public Registry $registry,
        public Db $db,
    ) {
    }
}

class Registry
{
    public array $processor;
}

class Schema
{
    public function __construct(public Db $db)
    {
    }
}
