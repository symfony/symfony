<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

/**
 * A rich domain object, intentionally free of mapping metadata, with a private
 * property that has no public getter (not readable through PropertyAccess) and
 * no counterpart on the target view.
 */
final class RichDomainUser
{
    public int $id = 1;
    public string $username = 'john';
    private array $reviews = []; // unreadable + absent from the target: triggers the guarded branch
}
