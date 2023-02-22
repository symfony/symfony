<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlSanitizerAllTest extends TestCase
{
    private function createSanitizer(): HtmlSanitizer
    {
        return new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowStaticElements()
                ->allowLinkHosts(['trusted.com', 'external.com'])
                ->allowMediaHosts(['trusted.com', 'external.com'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->forceHttpsUrls()
        );
    }

    /**
     * @dataProvider provideSanitizeHead
     */
    public function testSanitizeHead(string $input, string $expected)
    {
        $this->assertSame($expected, $this->createSanitizer()->sanitizeFor('head', $input));
    }

    public static function provideSanitizeHead()
    {
        $cases = [
            // Scripts
            [
                '<LINK REL="stylesheet" HREF="javascript:alert(\'XSS\');">',
                '<link rel="stylesheet" />',
            ],

            // Normal tags
            [
                '<link rel="stylesheet" href="http://trusted.com" />',
                '<link rel="stylesheet" href="https://trusted.com" />',
            ],
            [
                '<link rel="stylesheet" href="http://untrusted.com" />',
                '<link rel="stylesheet" />',
            ],
        ];

        foreach ($cases as $case) {
            yield $case[0] => $case;
        }
    }

    /**
     * @dataProvider provideSanitizeBody
     */
    public function testSanitizeBody(string $input, string $expected)
    {
        $this->assertSame($expected, $this->createSanitizer()->sanitize($input));
    }

    public static function provideSanitizeBody()
    {
        $cases = [
            // Text
            [
                'hello world',
                'hello world',
            ],
            [
                '&lt;hello world&gt;',
                '&lt;hello world&gt;',
            ],
            [
                '< Hello',
                ' Hello',
            ],
            [
                'Lorem & Ipsum',
                'Lorem &amp; Ipsum',
            ],

            // Unknown tag
            [
                '<unknown>Lorem ipsum</unknown>',
                '',
            ],

            // Scripts
            [
                '<script>alert(\'ok\');</script>',
                '',
            ],
            [
                'javascript:/*--></title></style></textarea></script></xmp><svg/onload=\'+/"/+/onmouseover=1/+/[*/[]/+alert(1)//\'>',
                'javascript:/*--&gt;',
            ],
            [
                '<scr<script>ipt>alert(1)</script>',
                '',
            ],
            [
                '<scr<a>ipt>alert(1)</script>',
                '',
            ],
            [
                '<noscript>Lorem ipsum</noscript>',
                '',
            ],
            [
                '<div>Lorem ipsum dolor sit amet, consectetur adipisicing elit.<script>alert(\'ok\');</script></div>',
                '<div>Lorem ipsum dolor sit amet, consectetur adipisicing elit.</div>',
            ],
            [
                '<a href="javascript:alert(\'ok\')">Lorem ipsum dolor sit amet, consectetur adipisicing elit.</a>',
                '<a>Lorem ipsum dolor sit amet, consectetur adipisicing elit.</a>',
            ],
            [
                '<<a href="javascript:evil"/>a href="javascript:evil"/>',
                '<a>a href&#61;&#34;javascript:evil&#34;/&gt;</a>',
            ],
            [
                '<a href="javascript:alert(\'ok\')">Test</a>',
                '<a>Test</a>',
            ],
            [
                '<a href="javascript://%0Aalert(document.cookie)">Test</a>',
                '<a>Test</a>',
            ],
            [
                '<a href="&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;&#97;&#108;&#101;&#114;&#116;&#40;&#39;&#88;&#83;&#83;&#39;&#41;">Lorem ipsum</a>',
                '<a>Lorem ipsum</a>',
            ],
            [
                '<a href="java\0&#14;\t\r\n script:alert(\\\'foo\\\')">Lorem ipsum</a>',
                '<a>Lorem ipsum</a>',
            ],
            [
                '<a href= onmouseover="alert(\\\'XSS\\\');">Lorem ipsum</a>',
                '<a href="onmouseover&#61;&#34;alert(\&#039;XSS\&#039;);&#34;">Lorem ipsum</a>',
            ],
            [
                '<a href="http://trusted.com" onclick="alert(\'ok\')">Test</a>',
                '<a href="https://trusted.com">Test</a>',
            ],
            [
                '<figure><img src="https://trusted.com/img/example.jpg" onclick="alert(\'ok\')" /></figure>',
                '<figure><img src="https://trusted.com/img/example.jpg" /></figure>',
            ],
            [
                '<img src= onmouseover="alert(\'XSS\');" />',
                '<img src="onmouseover&#61;&#34;alert(&#039;XSS&#039;);&#34;" />',
            ],
            [
                '<<img src="javascript:evil"/>iframe src="javascript:evil"/>',
                '<img />iframe src&#61;&#34;javascript:evil&#34;/&gt;',
            ],
            [
                '<<img src="javascript:evil"/>img src="javascript:evil"/>',
                '<img />img src&#61;&#34;javascript:evil&#34;/&gt;',
            ],
            [
                '<IMG SRC="javascript:alert(\'XSS\');">',
                '<img />',
            ],
            [
                '<IMG SRC=javascript:alert(\'XSS\')>',
                '<img />',
            ],
            [
                '<IMG SRC=JaVaScRiPt:alert(\'XSS\')>',
                '<img />',
            ],
            [
                '<IMG SRC=javascript:alert(&quot;XSS&quot;)>',
                '<img />',
            ],
            [
                '<IMG SRC=`javascript:alert("RSnake says, \'XSS\'")`>',
                '<img />',
            ],
            [
                '<IMG """><SCRIPT>alert("XSS")</SCRIPT>"\>',
                '<img />&#34;\&gt;',
            ],
            [
                '<IMG SRC=javascript:alert(String.fromCharCode(88,83,83))>',
                '<img />',
            ],
            [
                '<IMG SRC=# onmouseover="alert(\'xxs\')">',
                '<img src="#" />',
            ],
            [
                '<img src=x onerror="&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000114&#0000105&#0000112&#0000116&#0000058&#0000097&#0000108&#0000101&#0000114&#0000116&#0000040&#0000039&#0000088&#0000083&#0000083&#0000039&#0000041">',
                '<img src="x" />',
            ],
            [
                '<IMG SRC=&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;&#97;&#108;&#101;&#114;&#116;&#40;&#39;&#88;&#83;&#83;&#39;&#41;>',
                '<img />',
            ],
            [
                '<IMG SRC=&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000114&#0000105&#0000112&#0000116&#0000058&#0000097&#0000108&#0000101&#0000114&#0000116&#0000040&#0000039&#0000088&#0000083&#0000083&#0000039&#0000041>',
                '<img src="&amp;#0000106&amp;#0000097&amp;#0000118&amp;#0000097&amp;#0000115&amp;#0000099&amp;#0000114&amp;#0000105&amp;#0000112&amp;#0000116&amp;#0000058&amp;#0000097&amp;#0000108&amp;#0000101&amp;#0000114&amp;#0000116&amp;#0000040&amp;#0000039&amp;#0000088&amp;#0000083&amp;#0000083&amp;#0000039&amp;#0000041" />',
            ],
            [
                '<IMG SRC=&#x6A&#x61&#x76&#x61&#x73&#x63&#x72&#x69&#x70&#x74&#x3A&#x61&#x6C&#x65&#x72&#x74&#x28&#x27&#x58&#x53&#x53&#x27&#x29>',
                '<img src="&amp;#x6A&amp;#x61&amp;#x76&amp;#x61&amp;#x73&amp;#x63&amp;#x72&amp;#x69&amp;#x70&amp;#x74&amp;#x3A&amp;#x61&amp;#x6C&amp;#x65&amp;#x72&amp;#x74&amp;#x28&amp;#x27&amp;#x58&amp;#x53&amp;#x53&amp;#x27&amp;#x29" />',
            ],
            [
                '<IMG DYNSRC="javascript:alert(\'XSS\')">',
                '<img />',
            ],
            [
                '<IMG LOWSRC="javascript:alert(\'XSS\')">',
                '<img />',
            ],
            [
                '<IMG SRC=\'vbscript:msgbox(\"XSS")\'>',
                '<img />',
            ],
            [
                '<svg/onload=alert(\'XSS\')>',
                '',
            ],
            [
                '<BODY BACKGROUND="javascript:alert(\'XSS\')">',
                '<body></body>',
            ],
            [
                '<BGSOUND SRC="javascript:alert(\'XSS\');">',
                '<bgsound></bgsound>',
            ],
            [
                '<BR SIZE="&{alert(\'XSS\')}">',
                '<br size="&amp;{alert(&#039;XSS&#039;)}" />',
            ],
            [
                '<BR></br>',
                '<br /><br />',
            ],

            [
                '<OBJECT TYPE="text/x-scriptlet" DATA="http://xss.rocks/scriptlet.html"></OBJECT>',
                '',
            ],
            [
                '<EMBED SRC="http://ha.ckers.org/xss.swf" AllowScriptAccess="always"></EMBED>',
                '',
            ],
            [
                '<EMBED SRC="data:image/svg+xml;base64,PHN2ZyB4bWxuczpzdmc9Imh0dH A6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcv MjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hs aW5rIiB2ZXJzaW9uPSIxLjAiIHg9IjAiIHk9IjAiIHdpZHRoPSIxOTQiIGhlaWdodD0iMjAw IiBpZD0ieHNzIj48c2NyaXB0IHR5cGU9InRleHQvZWNtYXNjcmlwdCI+YWxlcnQoIlh TUyIpOzwvc2NyaXB0Pjwvc3ZnPg==" type="image/svg+xml" AllowScriptAccess="always"></EMBED>',
                '',
            ],
            [
                '!<textarea>&lt;/textarea&gt;&lt;svg/onload=prompt`xs`&gt;</textarea>!',
                '!<textarea>&lt;/textarea&gt;&lt;svg/onload&#61;prompt&#96;xs&#96;&gt;</textarea>!',
            ],

            // Inspired by https://www.youtube.com/watch?v=kz7wmRV9xsU
            [
                '＜script＞alert(\'ok\');＜/script＞',
                '&#xFF1C;script&#xFF1E;alert(&#039;ok&#039;);&#xFF1C;/script&#xFF1E;',
            ],

            // Inspired by https://twitter.com/brutelogic/status/1066333383276593152?s=19
            [
                '"><svg/onload=confirm(1)>"@x.y',
                '&#34;&gt;',
            ],

            // Styles
            [
                '<style>body { background: red; }</style>',
                '',
            ],
            [
                '<div>Lorem ipsum dolor sit amet, consectetur.<style>body { background: red; }</style></div>',
                '<div>Lorem ipsum dolor sit amet, consectetur.</div>',
            ],
            [
                '<img src="https://trusted.com/img/example.jpg" style="position:absolute;top:0;left:0;width:9000px;height:9000px;" />',
                '<img src="https://trusted.com/img/example.jpg" style="position:absolute;top:0;left:0;width:9000px;height:9000px;" />',
            ],
            [
                '<a style="font-size: 40px; color: red;">Lorem ipsum dolor sit amet, consectetur.</a>',
                '<a style="font-size: 40px; color: red;">Lorem ipsum dolor sit amet, consectetur.</a>',
            ],

            // Comments
            [
                'Lorem ipsum dolor sit amet, consectetur<!--if[true]> <script>alert(1337)</script> -->',
                'Lorem ipsum dolor sit amet, consectetur',
            ],
            [
                'Lorem ipsum<![CDATA[ <!-- ]]> <script>alert(1337)</script> <!-- -->',
                'Lorem ipsum  ',
            ],

            // Normal tags
            [
                '<abbr>Lorem ipsum</abbr>',
                '<abbr>Lorem ipsum</abbr>',
            ],
            [
                '<a>Lorem ipsum</a>',
                '<a>Lorem ipsum</a>',
            ],
            [
                '<a href="http://trusted.com/index.html#this:stuff">Lorem ipsum</a>',
                '<a href="https://trusted.com/index.html#this:stuff">Lorem ipsum</a>',
            ],
            [
                '<a href="https://trusted.com" title="Link title">Lorem ipsum</a>',
                '<a href="https://trusted.com" title="Link title">Lorem ipsum</a>',
            ],
            [
                '<a href="https://untrusted.com" title="Link title">Lorem ipsum</a>',
                '<a title="Link title">Lorem ipsum</a>',
            ],
            [
                '<a href="https://external.com" title="Link title">Lorem ipsum</a>',
                '<a href="https://external.com" title="Link title">Lorem ipsum</a>',
            ],
            [
                '<a href="mailto:test&#64;gmail.com" title="Link title">Lorem ipsum</a>',
                '<a href="mailto:test&#64;gmail.com" title="Link title">Lorem ipsum</a>',
            ],
            [
                '<blockquote>Lorem ipsum</blockquote>',
                '<blockquote>Lorem ipsum</blockquote>',
            ],
            [
                'Lorem ipsum <br>dolor sit amet <br />consectetur adipisicing.',
                'Lorem ipsum <br />dolor sit amet <br />consectetur adipisicing.',
            ],
            [
                '<caption>Lorem ipsum</caption>',
                '<caption>Lorem ipsum</caption>',
            ],
            [
                '<code>Lorem ipsum</code>',
                '<code>Lorem ipsum</code>',
            ],
            [
                '<dd>Lorem ipsum</dd>',
                '<dd>Lorem ipsum</dd>',
            ],
            [
                '<del>Lorem ipsum</del>',
                '<del>Lorem ipsum</del>',
            ],
            [
                '<details>Lorem ipsum</details>',
                '<details>Lorem ipsum</details>',
            ],
            [
                '<details open>Lorem ipsum</details>',
                '<details open>Lorem ipsum</details>',
            ],
            [
                '<details open="foo">Lorem ipsum</details>',
                '<details open="foo">Lorem ipsum</details>',
            ],
            [
                '<div>Lorem ipsum dolor sit amet, consectetur adipisicing elit.</div>',
                '<div>Lorem ipsum dolor sit amet, consectetur adipisicing elit.</div>',
            ],
            [
                '<dl>Lorem ipsum</dl>',
                '<dl>Lorem ipsum</dl>',
            ],
            [
                '<dt>Lorem ipsum</dt>',
                '<dt>Lorem ipsum</dt>',
            ],
            [
                '<em>Lorem ipsum</em>',
                '<em>Lorem ipsum</em>',
            ],
            [
                '<figcaption>Lorem ipsum</figcaption>',
                '<figcaption>Lorem ipsum</figcaption>',
            ],
            [
                '<figure>Lorem ipsum</figure>',
                '<figure>Lorem ipsum</figure>',
            ],
            [
                '<h1>Lorem ipsum</h1>',
                '<h1>Lorem ipsum</h1>',
            ],
            [
                '<h2>Lorem ipsum</h2>',
                '<h2>Lorem ipsum</h2>',
            ],
            [
                '<h3>Lorem ipsum</h3>',
                '<h3>Lorem ipsum</h3>',
            ],
            [
                '<h4>Lorem ipsum</h4>',
                '<h4>Lorem ipsum</h4>',
            ],
            [
                '<h5>Lorem ipsum</h5>',
                '<h5>Lorem ipsum</h5>',
            ],
            [
                '<h6>Lorem ipsum</h6>',
                '<h6>Lorem ipsum</h6>',
            ],
            [
                '<hr />',
                '<hr />',
            ],
            [
                '<img src="/img/example.jpg" alt="Image alternative text" title="Image title">',
                '<img src="/img/example.jpg" alt="Image alternative text" title="Image title" />',
            ],
            [
                '<img src="http://trusted.com/img/example.jpg" alt="Image alternative text" title="Image title" />',
                '<img src="https://trusted.com/img/example.jpg" alt="Image alternative text" title="Image title" />',
            ],
            [
                '<img src="http://untrusted.com/img/example.jpg" alt="Image alternative text" title="Image title" />',
                '<img alt="Image alternative text" title="Image title" />',
            ],
            [
                '<img />',
                '<img />',
            ],
            [
                '<img title="" />',
                '<img title />',
            ],
            [
                '<i>Lorem ipsum</i>',
                '<i>Lorem ipsum</i>',
            ],
            [
                '<i></i>',
                '<i></i>',
            ],

            [
                '<li>Lorem ipsum</li>',
                '<li>Lorem ipsum</li>',
            ],
            [
                '<mark>Lorem ipsum</mark>',
                '<mark>Lorem ipsum</mark>',
            ],
            [
                '<ol>Lorem ipsum</ol>',
                '<ol>Lorem ipsum</ol>',
            ],
            [
                '<p>Lorem ipsum</p>',
                '<p>Lorem ipsum</p>',
            ],
            [
                '<pre>Lorem ipsum</pre>',
                '<pre>Lorem ipsum</pre>',
            ],
            [
                '<q>Lorem ipsum</q>',
                '<q>Lorem ipsum</q>',
            ],
            [
                '<rp>Lorem ipsum</rp>',
                '<rp>Lorem ipsum</rp>',
            ],
            [
                '<rt>Lorem ipsum</rt>',
                '<rt>Lorem ipsum</rt>',
            ],
            [
                '<ruby>Lorem ipsum</ruby>',
                '<ruby>Lorem ipsum</ruby>',
            ],
            [
                '<small>Lorem ipsum</small>',
                '<small>Lorem ipsum</small>',
            ],
            [
                '<span>Lorem ipsum</span>',
                '<span>Lorem ipsum</span>',
            ],
            [
                '<strong>Lorem ipsum</strong>',
                '<strong>Lorem ipsum</strong>',
            ],
            [
                '<summary>Lorem ipsum</summary>',
                '<summary>Lorem ipsum</summary>',
            ],
            [
                '<time datetime="2018-12-25 00:00">Lorem ipsum</time>',
                '<time datetime="2018-12-25 00:00">Lorem ipsum</time>',
            ],
            [
                '<b>Lorem ipsum</b>',
                '<b>Lorem ipsum</b>',
            ],
            [
                '<sub>Lorem ipsum</sub>',
                '<sub>Lorem ipsum</sub>',
            ],
            [
                '<sup>Lorem ipsum</sup>',
                '<sup>Lorem ipsum</sup>',
            ],
            [
                '<table>Lorem ipsum</table>',
                '<table>Lorem ipsum</table>',
            ],
            [
                '<tbody>Lorem ipsum</tbody>',
                '<tbody>Lorem ipsum</tbody>',
            ],
            [
                '<td>Lorem ipsum</td>',
                '<td>Lorem ipsum</td>',
            ],
            [
                '<tfoot>Lorem ipsum</tfoot>',
                '<tfoot>Lorem ipsum</tfoot>',
            ],
            [
                '<thead>Lorem ipsum</thead>',
                '<thead>Lorem ipsum</thead>',
            ],
            [
                '<th>Lorem ipsum</th>',
                '<th>Lorem ipsum</th>',
            ],
            [
                '<tr>Lorem ipsum</tr>',
                '<tr>Lorem ipsum</tr>',
            ],
            [
                '<ul>Lorem ipsum</ul>',
                '<ul>Lorem ipsum</ul>',
            ],
        ];

        foreach ($cases as $case) {
            yield $case[0] => $case;
        }
    }
}
