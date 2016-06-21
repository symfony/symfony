<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler\ClassNamedServices;

interface IA {
}

interface IC
{
}

interface IB extends IC
{
}

class C implements IB
{
}

class B extends C
{
}

class A extends B implements IA
{
}

class E
{
}
