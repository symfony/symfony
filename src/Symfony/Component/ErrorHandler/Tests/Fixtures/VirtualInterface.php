<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

/**
 * @method string interfaceMethod()
 * @method static staticReturningMethod()
 * @method        sameLineInterfaceMethod($arg)
 * @method sameLineInterfaceMethodNoBraces
 *
 * Ignored
 * @method
 * @method
 *
 * Not ignored
 * @method newLineInterfaceMethod() Some description!
 * @method \stdClass newLineInterfaceMethodNoBraces Description
 *
 * Invalid
 * @method unknownType invalidInterfaceMethod()
 * @method unknownType|string invalidInterfaceMethodNoBraces
 *
 * Complex
 * @method              complexInterfaceMethod($arg, ...$args)
 * @method string[]|int complexInterfaceMethodTyped($arg, int ...$args) Description ...
 *
 * Static
 * @method static Foo&Bar staticMethod()
 * @method static mixed staticMethodNoBraces
 * @method static \stdClass staticMethodTyped(int $arg) Description
 * @method static \stdClass[] staticMethodTypedNoBraces
 */
interface VirtualInterface
{
}
