<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Caster\FFICaster;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @author Kirill Nesmeyanov <nesk@xakep.ru>
 *
 * @requires extension ffi
 */
class FFICasterTest extends TestCase
{
    use VarDumperTestTrait;

    protected function setUp(): void
    {
        if (\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) && 'preload' === \ini_get('ffi.enable')) {
            return;
        }
        if (!filter_var(\ini_get('ffi.enable'), \FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('FFI not enabled for CLI SAPI');
        }
    }

    public function testCastAnonymousStruct()
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<struct <anonymous>> size 4 align 4 {
          uint32_t x: 0
        }
        PHP, \FFI::new('struct { uint32_t x; }'));
    }

    public function testCastNamedStruct()
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<struct Example> size 4 align 4 {
          uint32_t x: 0
        }
        PHP, \FFI::new('struct Example { uint32_t x; }'));
    }

    public function testCastAnonymousUnion()
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<union <anonymous>> size 4 align 4 {
          uint32_t x: 0
          uint32_t y: 0
        }
        PHP, \FFI::new('union { uint32_t x; uint32_t y; }'));
    }

    public function testCastNamedUnion()
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<union Example> size 4 align 4 {
          uint32_t x: 0
          uint32_t y: 0
        }
        PHP, \FFI::new('union Example { uint32_t x; uint32_t y; }'));
    }

    public function testCastAnonymousEnum()
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<enum <anonymous>> size 4 align 4 {
          cdata: 0
        }
        PHP, \FFI::new('enum { a, b }'));
    }

    public function testCastNamedEnum()
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<enum Example> size 4 align 4 {
          cdata: 0
        }
        PHP, \FFI::new('enum Example { a, b }'));
    }

    public static function scalarsDataProvider(): array
    {
        return [
            'int8_t' => ['int8_t', '0', 1, 1],
            'uint8_t' => ['uint8_t', '0', 1, 1],
            'int16_t' => ['int16_t', '0', 2, 2],
            'uint16_t' => ['uint16_t', '0', 2, 2],
            'int32_t' => ['int32_t', '0', 4, 4],
            'uint32_t' => ['uint32_t', '0', 4, 4],
            'int64_t' => ['int64_t', '0', 8, 8],
            'uint64_t' => ['uint64_t', '0', 8, 8],

            'bool' => ['bool', 'false', 1, 1],
            'char' => ['char', '"\x00"', 1, 1],
            'float' => ['float', '0.0', 4, 4],
            'double' => ['double', '0.0', 8, 8],
        ];
    }

    /**
     * @dataProvider scalarsDataProvider
     */
    public function testCastScalar(string $type, string $value, int $size, int $align)
    {
        $this->assertDumpEquals(<<<PHP
        FFI\CData<$type> size $size align $align {
          cdata: $value
        }
        PHP, \FFI::new($type));
    }

    public function testCastVoidFunction()
    {
        $abi = \PHP_OS_FAMILY === 'Windows' ? '[cdecl]' : '[fastcall]';

        $this->assertDumpEquals(<<<PHP
        $abi callable(): void {
          returnType: FFI\CType<void> size 1 align 1 {}
        }
        PHP, \FFI::new('void (*)(void)'));
    }

    public function testCastIntFunction()
    {
        $abi = \PHP_OS_FAMILY === 'Windows' ? '[cdecl]' : '[fastcall]';

        $this->assertDumpEquals(<<<PHP
        $abi callable(): uint64_t {
          returnType: FFI\CType<uint64_t> size 8 align 8 {}
        }
        PHP, \FFI::new('unsigned long long (*)(void)'));
    }

    public function testCastFunctionWithArguments()
    {
        $abi = \PHP_OS_FAMILY === 'Windows' ? '[cdecl]' : '[fastcall]';

        $this->assertDumpEquals(<<<PHP
        $abi callable(int32_t, char*): void {
          returnType: FFI\CType<void> size 1 align 1 {}
        }
        PHP, \FFI::new('void (*)(int a, const char* b)'));
    }

    public function testCastNonCuttedPointerToChar()
    {
        $actualMessage = "Hello World!\0";

        $string = \FFI::new('char[100]');
        $pointer = \FFI::addr($string[0]);
        \FFI::memcpy($pointer, $actualMessage, \strlen($actualMessage));

        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<char*> size 8 align 8 {
          cdata: "Hello World!\x00"
        }
        PHP, $pointer);
    }

    public function testCastCuttedPointerToChar()
    {
        $actualMessage = str_repeat('Hello World!', 30)."\0";
        $actualLength = \strlen($actualMessage);

        $expectedMessage = 'Hello World!Hello World!Hello World!Hello World!'
            .'Hello World!Hello World!Hello World!Hello World!Hello World!Hel'
            .'lo World!Hello World!Hello World!Hello World!Hello World!Hello '
            .'World!Hello World!Hello World!Hello World!Hello World!Hello Wor'
            .'ld!Hello World!Hel';

        $string = \FFI::new('char['.$actualLength.']');
        $pointer = \FFI::addr($string[0]);
        \FFI::memcpy($pointer, $actualMessage, $actualLength);

        $this->assertDumpEquals(<<<PHP
        FFI\CData<char*> size 8 align 8 {
          cdata: "$expectedMessage"…
        }
        PHP, $pointer);
    }

    /**
     * It is worth noting that such a test can cause SIGSEGV, as it breaks
     * into "foreign" memory. However, this is only theoretical, since
     * memory is allocated within the PHP process and almost always "garbage
     * data" will be read from the PHP process itself.
     *
     * If this test fails for some reason, please report it: We may have to
     * disable the dumping of strings ("char*") feature in VarDumper.
     *
     * @see FFICaster::castFFIStringValue()
     */
    public function testCastNonTrailingCharPointer()
    {
        $actualMessage = 'Hello World!';
        $actualLength = \strlen($actualMessage);

        $string = \FFI::new('char['.$actualLength.']');
        $pointer = \FFI::addr($string[0]);

        \FFI::memcpy($pointer, $actualMessage, $actualLength);

        // Remove automatically addition of the trailing "\0" and remove trailing "\0"
        $pointer = \FFI::cast('char*', \FFI::cast('void*', $pointer));
        $pointer[$actualLength] = "\x01";

        $this->assertDumpMatchesFormat(<<<PHP
        FFI\CData<char*> size 8 align 8 {
          cdata: "$actualMessage%s"
        }
        PHP, $pointer);
    }

    public function testCastUnionWithDirectReferencedFields()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef union Event {
            int32_t x;
            float y;
        } Event;
        CPP);

        $this->assertDumpEquals(<<<'OUTPUT'
        FFI\CData<union Event> size 4 align 4 {
          int32_t x: 0
          float y: 0.0
        }
        OUTPUT, $ffi->new('Event'));
    }

    public function testCastUnionWithPointerReferencedFields()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef union Event {
            void* something;
            char* string;
        } Event;
        CPP);

        $this->assertDumpEquals(<<<'OUTPUT'
        FFI\CData<union Event> size 8 align 8 {
          something?: FFI\CType<void*> size 8 align 8 {
            0: FFI\CType<void> size 1 align 1 {}
          }
          string?: FFI\CType<char*> size 8 align 8 {
            0: FFI\CType<char> size 1 align 1 {}
          }
        }
        OUTPUT, $ffi->new('Event'));
    }

    public function testCastUnionWithMixedFields()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef union Event {
            void* a;
            int32_t b;
            char* c;
            ptrdiff_t d;
        } Event;
        CPP);

        $this->assertDumpEquals(<<<'OUTPUT'
        FFI\CData<union Event> size 8 align 8 {
          a?: FFI\CType<void*> size 8 align 8 {
            0: FFI\CType<void> size 1 align 1 {}
          }
          int32_t b: 0
          c?: FFI\CType<char*> size 8 align 8 {
            0: FFI\CType<char> size 1 align 1 {}
          }
          int64_t d: 0
        }
        OUTPUT, $ffi->new('Event'));
    }

    public function testCastPointerToEmptyScalars()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef struct {
            int8_t *a;
            uint8_t *b;
            int64_t *c;
            uint64_t *d;
            float *e;
            double *f;
            bool *g;
        } Example;
        CPP);

        $this->assertDumpEquals(<<<'OUTPUT'
        FFI\CData<struct <anonymous>> size 56 align 8 {
          int8_t* a: null
          uint8_t* b: null
          int64_t* c: null
          uint64_t* d: null
          float* e: null
          double* f: null
          bool* g: null
        }
        OUTPUT, $ffi->new('Example'));
    }

    public function testCastPointerToNonEmptyScalars()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef struct {
            int8_t *a;
            uint8_t *b;
            int64_t *c;
            uint64_t *d;
            float *e;
            double *f;
            bool *g;
        } Example;
        CPP);

        // Create values
        $int = \FFI::new('int64_t');
        $int->cdata = 42;
        $float = \FFI::new('float');
        $float->cdata = 42.0;
        $double = \FFI::new('double');
        $double->cdata = 42.2;
        $bool = \FFI::new('bool');
        $bool->cdata = true;

        // Fill struct
        $struct = $ffi->new('Example');
        $struct->a = \FFI::addr(\FFI::cast('int8_t', $int));
        $struct->b = \FFI::addr(\FFI::cast('uint8_t', $int));
        $struct->c = \FFI::addr(\FFI::cast('int64_t', $int));
        $struct->d = \FFI::addr(\FFI::cast('uint64_t', $int));
        $struct->e = \FFI::addr(\FFI::cast('float', $float));
        $struct->f = \FFI::addr(\FFI::cast('double', $double));
        $struct->g = \FFI::addr(\FFI::cast('bool', $bool));

        $this->assertDumpEquals(<<<'OUTPUT'
        FFI\CData<struct <anonymous>> size 56 align 8 {
          a: FFI\CData<int8_t*> size 8 align 8 {
            cdata: 42
          }
          b: FFI\CData<uint8_t*> size 8 align 8 {
            cdata: 42
          }
          c: FFI\CData<int64_t*> size 8 align 8 {
            cdata: 42
          }
          d: FFI\CData<uint64_t*> size 8 align 8 {
            cdata: 42
          }
          e: FFI\CData<float*> size 8 align 8 {
            cdata: 42.0
          }
          f: FFI\CData<double*> size 8 align 8 {
            cdata: 42.2
          }
          g: FFI\CData<bool*> size 8 align 8 {
            cdata: true
          }
        }
        OUTPUT, $struct);
    }

    public function testCastPointerToStruct()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef struct {
            int8_t a;
        } Example;
        CPP);

        $struct = $ffi->new('Example', false);

        $this->assertDumpEquals(<<<'OUTPUT'
        FFI\CData<struct <anonymous>*> size 8 align 8 {
          cdata: FFI\CData<struct <anonymous>> size 1 align 1 {
            int8_t a: 0
          }
        }
        OUTPUT, \FFI::addr($struct));

        // Save the pointer as variable so that
        // it is not cleaned up by the GC
        $pointer = \FFI::addr($struct);

        $this->assertDumpEquals(<<<'OUTPUT'
        FFI\CData<struct <anonymous>**> size 8 align 8 {
          cdata: FFI\CData<struct <anonymous>*> size 8 align 8 {
            cdata: FFI\CData<struct <anonymous>> size 1 align 1 {
              int8_t a: 0
            }
          }
        }
        OUTPUT, \FFI::addr($pointer));

        \FFI::free($struct);
    }

    public function testCastComplexType()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef struct {
            int x;
            int y;
        } Point;
        typedef struct Example {
            uint8_t a[32];
            long b;
            __extension__ union {
                __extension__ struct {
                    short c;
                    long d;
                };
                struct {
                    Point point;
                    float e;
                };
            };
            short f;
            bool g;
            int (*func)(
                struct __sub *h
            );
        } Example;
        CPP);

        $var = $ffi->new('Example');
        $var->func = (static fn (object $p) => 42);

        $abi = \PHP_OS_FAMILY === 'Windows' ? '[cdecl]' : '[fastcall]';
        $longSize = \FFI::type('long')->getSize();
        $longType = 8 === $longSize ? 'int64_t' : 'int32_t';
        $structSize = 56 + $longSize * 2;

        $this->assertDumpEquals(<<<OUTPUT
        FFI\CData<struct Example> size $structSize align 8 {
          a: FFI\CData<uint8_t[32]> size 32 align 1 {}
          $longType b: 0
          int16_t c: 0
          $longType d: 0
          point: FFI\CData<struct <anonymous>> size 8 align 4 {
            int32_t x: 0
            int32_t y: 0
          }
          float e: 0.0
          int16_t f: 0
          bool g: false
          func: $abi callable(struct __sub*): int32_t {
            returnType: FFI\CType<int32_t> size 4 align 4 {}
          }
        }
        OUTPUT, $var);
    }
}
