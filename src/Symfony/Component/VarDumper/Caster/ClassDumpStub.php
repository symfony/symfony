<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Represents a class dumped from its name: source location and static properties.
 */
class ClassDumpStub extends Stub
{
    public function __construct(string $class)
    {
        $r = new \ReflectionClass($class);

        $this->type = self::TYPE_OBJECT;
        $this->class = $class;

        if ($f = $r->getFileName()) {
            $this->attr['file'] = $f;
            $this->attr['line'] = $r->getStartLine();
        }

        $properties = [];
        foreach ($r->getProperties(\ReflectionProperty::IS_STATIC) as $p) {
            $key = match (true) {
                $p->isPublic() => $p->getName(),
                $p->isProtected() => Caster::PREFIX_PROTECTED.$p->getName(),
                default => sprintf(Caster::PATTERN_PRIVATE, $class, $p->getName()),
            };
            $properties["$key (static)"] = $p->isInitialized() ? $p->getValue() : new UninitializedStub($p);
        }

        $this->value = $properties;
    }
}
