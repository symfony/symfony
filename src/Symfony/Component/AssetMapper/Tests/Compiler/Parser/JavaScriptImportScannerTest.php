<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\Compiler\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\Compiler\Parser\JavaScriptImportScanner;

class JavaScriptImportScannerTest extends TestCase
{
    /**
     * @param list<array{string, bool}> $expectedImports
     */
    #[DataProvider('provideStaticImports')]
    #[DataProvider('provideDynamicImports')]
    #[DataProvider('provideNonImports')]
    #[DataProvider('provideStringsCommentsAndTemplates')]
    #[DataProvider('provideRegularExpressions')]
    #[DataProvider('provideDivisions')]
    #[DataProvider('provideBrokenCode')]
    public function testScan(string $code, array $expectedImports)
    {
        $imports = [];
        foreach (JavaScriptImportScanner::scan($code) as [$offset, $specifier, $isDynamic]) {
            $this->assertSame($specifier, substr($code, $offset, \strlen($specifier)));
            $imports[] = [$specifier, $isDynamic];
        }

        $this->assertSame($expectedImports, $imports);
    }

    public static function provideStaticImports(): iterable
    {
        yield 'side effect' => ["import './a.js';", [['./a.js', false]]];
        yield 'default binding' => ['import a from "./a.js";', [['./a.js', false]]];
        yield 'named bindings' => ["import { a, b as c } from './a.js';", [['./a.js', false]]];
        yield 'namespace' => ["import * as a from './a.js';", [['./a.js', false]]];
        yield 'default and named bindings' => ["import a, { b } from './a.js';", [['./a.js', false]]];
        yield 'default and namespace' => ["import a, * as b from './a.js';", [['./a.js', false]]];
        yield 'bare specifier' => ["import { Controller } from '@hotwired/stimulus';", [['@hotwired/stimulus', false]]];
        yield 'minified' => [
            'import{a}from"./a.js";import*as b from"./b.js";import c,{d}from"./c.js";import"./d.js";import e from"./e.js";',
            [['./a.js', false], ['./b.js', false], ['./c.js', false], ['./d.js', false], ['./e.js', false]],
        ];
        yield 'dollar and underscore in names' => ["import $ from './a.js';\nimport { \$on, _off } from './b.js';", [['./a.js', false], ['./b.js', false]]];
        yield 'unicode names' => ['import { ɵfoo, été } from "./a.js";', [['./a.js', false]]];
        yield 'string names' => ["import { \"a-b\" as ab, 'c d' as cd } from './a.js';", [['./a.js', false]]];
        yield 'multiline' => ["import {\n    a,\n    b,\n} from './a.js';", [['./a.js', false]]];
        yield 'CRLF line endings' => ["import {\r\n    a,\r\n} from './a.js';\r\nimport './b.js';\r\n", [['./a.js', false], ['./b.js', false]]];
        yield 'line comment in named bindings' => ["import {\n    a, // first\n    b,\n} from './a.js';", [['./a.js', false]]];
        yield 'block comment in named bindings' => ["import { a, /* b, */ c } from './a.js';", [['./a.js', false]]];
        yield 'comments everywhere' => ["import/* 1 */a/* 2 */from/* 3 */'./a.js'/* 4 */;", [['./a.js', false]]];
        yield 'specifier on the next line' => ["import\n    './a.js';", [['./a.js', false]]];
        yield 'binding named from' => ["import from from './a.js';\nimport { from as b } from './b.js';", [['./a.js', false], ['./b.js', false]]];
        yield 'import attributes' => ["import data from './data.json' with { type: 'json' };\nimport './b.js';", [['./data.json', false], ['./b.js', false]]];
        yield 'import phases' => ["import defer * as a from './a.js';\nimport source b from './b.wasm';", [['./a.js', false], ['./b.wasm', false]]];
        yield 'after other statements' => ["const a = 1;\nfunction b() {}\nimport c from './c.js';", [['./c.js', false]]];
        yield 'byte order mark' => ["\u{FEFF}import './a.js';", [['./a.js', false]]];
        yield 'non-breaking spaces' => ["a();\u{A0}import\u{A0}b\u{A0}from\u{A0}'./b.js';\u{A0}import\u{A0}(\u{A0}'./c.js'\u{A0});", [['./b.js', false], ['./c.js', true]]];
        yield 'other unicode spaces' => ["import\u{3000}{\u{2009}a\u{202F}}\u{2028}from\u{2029}'./a.js';", [['./a.js', false]]];
        yield 'unicode space in a specifier' => ["import './a\u{A0}b.js';", [["./a\u{A0}b.js", false]]];
        yield 'unicode space before a regular expression' => ["const a = b ||\u{A0}/'/.test(c); import('./a.js'); d('e');", [['./a.js', true]]];
        yield 'export all' => ["export * from './a.js';", [['./a.js', false]]];
        yield 'export all as namespace' => ["export * as a from './a.js';", [['./a.js', false]]];
        yield 'export named' => ["export { a, b as c, default } from './a.js';", [['./a.js', false]]];
        yield 'export string names' => ["export { \"a-b\" as c } from './a.js';\nexport * as \"d e\" from './b.js';", [['./a.js', false], ['./b.js', false]]];
        yield 'export minified' => ['export*from"./a.js";export{b}from"./b.js";export*as c from"./c.js";', [['./a.js', false], ['./b.js', false], ['./c.js', false]]];
        yield 'export multiline with comments' => ["export {\n    a, // first\n    b,\n} from './a.js';", [['./a.js', false]]];
        yield 'local exports' => ["export const a = 1;\nexport default a;\nexport { a };\nexport function b() {}\nexport class C {}", []];
        yield 'local export then import' => ["export { a };\nimport b from './b.js';", [['./b.js', false]]];
        yield 'local export without semicolon then string' => ["export { a }\n'./a.js';", []];
        yield 'local export without semicolon then import' => ["export { a }\nimport b from './b.js'", [['./b.js', false]]];
        yield 'import then export' => ["import a from './a.js';\nexport { a };\nexport * from './b.js';", [['./a.js', false], ['./b.js', false]]];
    }

    public static function provideDynamicImports(): iterable
    {
        yield 'single quotes' => ["import('./a.js');", [['./a.js', true]]];
        yield 'double quotes' => ['import("./a.js");', [['./a.js', true]]];
        yield 'template literal' => ['import(`./a.js`);', [['./a.js', true]]];
        yield 'awaited' => ["const a = await import('./a.js');", [['./a.js', true]]];
        yield 'then' => ["import('./a.js').then((a) => a.default());", [['./a.js', true]]];
        yield 'spaces and line breaks' => ["import (\n    './a.js'\n);", [['./a.js', true]]];
        yield 'comment before the specifier' => ["import(/* webpackChunkName: \"a\" */ './a.js');", [['./a.js', true]]];
        yield 'options' => ["import('./a.json', { with: { type: 'json' } });", [['./a.json', true]]];
        yield 'in a function' => ["button.addEventListener('click', async () => { (await import('./a.js')).run(); });", [['./a.js', true]]];
        yield 'in a template substitution' => ["const a = `\${(await import('./a.js')).default} and \${b}`;", [['./a.js', true]]];
        yield 'several' => ["import('./a.js'); import('./b.js');", [['./a.js', true], ['./b.js', true]]];
        yield 'mixed with static imports' => ["import './a.js';\nimport('./b.js');\nexport * from './c.js';", [['./a.js', false], ['./b.js', true], ['./c.js', false]]];
        yield 'spread' => ["const a = { ...await import('./a.js') };", [['./a.js', true]]];
        yield 'concatenation' => ["import('./locales/' + locale + '.js');", []];
        yield 'template literal with substitution' => ['import(`./locales/${locale}.js`);', []];
        yield 'variable' => ['import(a);', []];
    }

    public static function provideNonImports(): iterable
    {
        yield 'empty' => ['', []];
        yield 'no import' => ["const a = 'b';\nconsole.log(a);", []];
        yield 'method call' => ["loader.import('./a.js');", []];
        yield 'optional method call' => ["loader?.import('./a.js');", []];
        yield 'method call on the next line' => ["loader\n    .import('./a.js');", []];
        yield 'private method call' => ["this.#import('./a.js');", []];
        yield 'longer names' => ["\$import('./a.js'); reimport('./b.js'); imports('./c.js'); import\$('./d.js'); exports.a = './e.js';", []];
        yield 'import.meta' => ["const a = import.meta.url;\nconst b = import.meta.resolve('./b.js');", []];
        yield 'property names' => ["const a = { import: './a.js', export: './b.js' };", []];
        yield 'method names' => ["class A { import(a) { return './a.js'; } export() { return './b.js'; } }", []];
        yield 'member access' => ["a.import = './a.js'; a.export = './b.js';", []];
    }

    public static function provideStringsCommentsAndTemplates(): iterable
    {
        yield 'in a single-quoted string' => ["a('import \"./b.js\"'); import './a.js';", [['./a.js', false]]];
        yield 'in a double-quoted string' => ["a(\"import('./b.js')\"); import './a.js';", [['./a.js', false]]];
        yield 'after an escaped quote' => ["a(\"\\\" import('./b.js')\", '\\' import(\"./c.js\")'); import './a.js';", [['./a.js', false]]];
        yield 'after an escaped backslash' => ["a('\\\\'); import './a.js'; b('\\\\');", [['./a.js', false]]];
        yield 'in a string with a line continuation' => ["a('b \\\nimport \"./b.js\"'); import './a.js';", [['./a.js', false]]];
        yield 'in a line comment' => ["// import './b.js';\nimport './a.js';", [['./a.js', false]]];
        yield 'in a line comment after code' => ["a(); // import('./b.js')\nimport('./a.js');", [['./a.js', true]]];
        yield 'in a block comment' => ["/* import './b.js'; */ import './a.js';", [['./a.js', false]]];
        yield 'in a multiline block comment' => ["/**\n * import './b.js';\n */\nimport './a.js';", [['./a.js', false]]];
        yield 'in an unterminated block comment' => ["import './a.js';\n/* import './b.js';", [['./a.js', false]]];
        yield 'after an apostrophe in a comment' => ["// don't\nimport './a.js';\n/* don't */ import './b.js';", [['./a.js', false], ['./b.js', false]]];
        yield 'comment markers in strings' => ["a('// b'); import './a.js'; a('/* b'); import './b.js';", [['./a.js', false], ['./b.js', false]]];
        yield 'quotes in comments' => ["/* \" */ import './a.js'; // '\nimport './b.js';", [['./a.js', false], ['./b.js', false]]];
        yield 'in a template literal' => ["const a = `import './b.js'`; import './a.js';", [['./a.js', false]]];
        yield 'in a multiline template literal' => ["const a = `\nimport './b.js';\n`;\nimport './a.js';", [['./a.js', false]]];
        yield 'after quotes in a template literal' => ["const a = `don't \"`; import './a.js';", [['./a.js', false]]];
        yield 'after an escaped backtick' => ["const a = `\\` import('./b.js')`; import './a.js';", [['./a.js', false]]];
        yield 'after an escaped substitution' => ["const a = `\\\${ import('./b.js')`; import './a.js';", [['./a.js', false]]];
        yield 'after a dollar sign in a template literal' => ["const a = `$ import('./b.js') \$`; import './a.js';", [['./a.js', false]]];
        yield 'in a nested template literal' => ["const a = `b \${c(`import('./b.js')`)} d`; import './a.js';", [['./a.js', false]]];
        yield 'after a substitution' => ["const a = `\${b} import('./b.js')`; import './a.js';", [['./a.js', false]]];
        yield 'after an object in a substitution' => ["const a = `\${{ b: { c: 1 } }.b.c} import('./b.js')`; import './a.js';", [['./a.js', false]]];
        yield 'after a string in a substitution' => ["const a = `\${'}'} import('./b.js') \${\"`\"}`; import './a.js';", [['./a.js', false]]];
        yield 'after a tagged template literal' => ["const a = html`<a href=\"\${b}\">import('./b.js')</a>`; import './a.js';", [['./a.js', false]]];
        yield 'hashbang' => ["#!/usr/bin/env node 'a\nimport './a.js';", [['./a.js', false]]];
    }

    public static function provideRegularExpressions(): iterable
    {
        $prefixes = [
            'at the start' => '',
            'after an assignment' => 'const a = ',
            'after a parenthesis' => 'a(',
            'after a comma' => 'a(b, ',
            'after a bracket' => 'const a = [',
            'after a colon' => 'const a = { b: ',
            'after a question mark' => 'const a = b ? ',
            'after a negation' => 'const a = !',
            'after a logical operator' => 'const a = b && ',
            'after a semicolon' => 'a();',
            'after a block' => 'if (a) { b(); } ',
            'after an arrow' => 'const a = () => ',
            'after a condition' => 'if (a) ',
            'after a loop condition' => 'while (a) ',
            'after return' => 'function a() { return ',
            'after typeof' => 'const a = typeof ',
            'after else' => 'if (a) b(); else ',
            'after case' => 'switch (a) { case ',
            'after export default' => 'export default ',
            'after a spread' => 'const a = [...',
            'after a division' => 'const a = b / ',
            'after a comment' => 'const a = /* b */ ',
        ];

        foreach ($prefixes as $label => $prefix) {
            yield 'apostrophe in a regular expression '.$label => [$prefix."/'/.test(b); import('./a.js'); c('d');", [['./a.js', true]]];
        }

        yield 'double quote in a regular expression' => ["const a = b.replace(/\"/g, '&quot;');\nimport('./a.js');\nconst c = \"d\";", [['./a.js', true]]];
        yield 'backtick in a regular expression' => ["const a = /`/.test(b);\nimport('./a.js');\nconst c = `d`;", [['./a.js', true]]];
        yield 'comment marker in a regular expression' => ["const a = b.replace(/\\/*\$/, '');\nimport('./a.js');\n/* c */", [['./a.js', true]]];
        yield 'line comment marker in a regular expression' => ["const a = /https?:\\/\\//.test(b); import('./a.js');", [['./a.js', true]]];
        yield 'slash in a character class' => ["const a = /[/']/.test(b); import('./a.js'); c('d');", [['./a.js', true]]];
        yield 'bracket in a character class' => ["const a = /[\\]/']/.test(b); import('./a.js'); c('d');", [['./a.js', true]]];
        yield 'escaped slash' => ["const a = /\\/'/.test(b); import('./a.js'); c('d');", [['./a.js', true]]];
        yield 'braces and parentheses in a regular expression' => ["const a = `\${/[({]/.test(b)} import('./b.js')`; import('./a.js');", [['./a.js', true]]];
        yield 'import in a regular expression' => ["const a = /import('.\\/b.js')/.test(b); import('./a.js');", [['./a.js', true]]];
        yield 'flags' => ["const a = /'/gi.test(b) ? /'/v : /'/dy; import('./a.js'); c('d');", [['./a.js', true]]];
    }

    public static function provideDivisions(): iterable
    {
        $prefixes = [
            'after a name' => 'a',
            'after a number' => '1',
            'after a number with a dot' => '1.',
            'after a parenthesis' => '(a + b)',
            'after a bracket' => 'a[0]',
            'after a string' => "'a'",
            'after a template literal' => '`a`',
            'after an increment' => 'a++',
            'after a decrement' => 'a--',
            'after a regular expression' => '/a/',
            'after a regular expression with flags' => '/a/g',
            'after a property named as a keyword' => 'a.return',
            'after a call' => 'a(b)',
            'after a comment' => 'a /* b */',
        ];

        foreach ($prefixes as $label => $prefix) {
            yield 'division '.$label => ['const c = '.$prefix." / 2 + '/'; import('./a.js'); d('e');", [['./a.js', true]]];
        }

        yield 'divisions on one line' => ["const a = b / c / d; import('./a.js'); const e = f / g;", [['./a.js', true]]];
        yield 'division assignment' => ["a /= 2; import('./a.js'); b /= 3;", [['./a.js', true]]];
        yield 'division after a block' => ["const a = function () {} / 2;\nimport('./a.js');", [['./a.js', true]]];
    }

    public static function provideBrokenCode(): iterable
    {
        yield 'unterminated string' => ["const a = 'b;\nimport './a.js';", [['./a.js', false]]];
        yield 'unterminated template literal' => ["import './a.js';\nconst a = `b import('./b.js')", [['./a.js', false]]];
        yield 'unterminated specifier' => ["import './a.js\nimport './b.js';", [['./b.js', false]]];
        yield 'unbalanced closing brackets' => ["}}))\nimport './a.js';\n`\${a}`", [['./a.js', false]]];
        yield 'import and nothing else' => ['import', []];
        yield 'import and a brace' => ['import {', []];
        yield 'import and a name' => ['import { a', []];
        yield 'import without specifier' => ['import a from', []];
        yield 'import and a parenthesis' => ['import(', []];
        yield 'import and a quote' => ["import('", []];
        yield 'export and nothing else' => ['export', []];
        yield 'export and a brace' => ['export {', []];
        yield 'export without specifier' => ['export * from', []];
        yield 'import with an assignment' => ["import { a = 1 } from './a.js'; import './b.js';", [['./b.js', false]]];
        yield 'import without from' => ["import a './a.js'; import './b.js';", [['./b.js', false]]];
        yield 'many import keywords' => [str_repeat('import ', 20000)."'./a.js'", []];
        yield 'many export keywords' => [str_repeat('export { a } ', 20000)."from './a.js'", [['./a.js', false]]];
    }
}
