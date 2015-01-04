<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class UrlValidator extends ConstraintValidator
{
    /**
     * IPv4 pattern
     */
    const PATTERN_HOST_IP_V4 = '
        (?<ipv4>
            (?<ipv4Dec>25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)
            (?:\.(?P>ipv4Dec)){3}
        ) # a IPv4 address
    ';

    /**
     * IPv6 pattern
     */
    const PATTERN_HOST_IP_V6 = '
        (?<ipv6>
            \[
            (?:
              (?:
                (?<ipv6h16>[0-9a-f]{1,4}):(?:(?P>ipv6h16):){5}
                (?<ipv6ls32>
                  (?:(?P>ipv6h16):(?P>ipv6h16))
                  |
                  (?<ipv6ipv4>
                    (?<ipv6ipv4Dec>25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)
                    (?:\.(?P>ipv6ipv4Dec)){3}
                  )
                )
              ) # 6( h16 ":" ) ls32
              |
              (?:::(?:(?P>ipv6h16):){5}(?P>ipv6ls32)) # "::" 5( h16 ":" ) ls32
              |
              (?:(?P>ipv6h16)?::(?:(?P>ipv6h16):){4}(?P>ipv6ls32)) # [ h16 ] "::" 4( h16 ":" ) ls32
              |
              (?:(?:(?:(?P>ipv6h16):){1,2}|:):(?:(?P>ipv6h16):){3}(?P>ipv6ls32)) # [ *1( h16 ":" ) h16 ] "::" 3( h16 ":" ) ls32
              |
              (?:(?:(?:(?P>ipv6h16):){1,3}|:):(?:(?P>ipv6h16):){2}(?P>ipv6ls32)) # [ *2( h16 ":" ) h16 ] "::" 2( h16 ":" ) ls32
              |
              (?:(?:(?:(?P>ipv6h16):){1,4}|:):(?P>ipv6h16):(?P>ipv6ls32)) # [ *3( h16 ":" ) h16 ] "::" h16 ":" ls32
              |
              (?:(?:(?:(?P>ipv6h16):){1,5}|:):(?P>ipv6ls32)) # [ *4( h16 ":" ) h16 ] "::" ls32
              |
              (?:(?:(?:(?P>ipv6h16):){1,6}|:):(?P>ipv6h16)) # [ *5( h16 ":" ) h16 ] "::" h16
              |
              (?:(?:(?:(?P>ipv6h16):){1,7}|:):) # [ *6( h16 ":" ) h16 ] "::"
            )
            \]
        ) # a IPv6 address
    ';

    /**
     * IP future pattern.
     * Used by RFC3986 (http://tools.ietf.org/html/rfc3986#section-3.2.2).
     */
    const PATTERN_HOST_IP_FUTURE = '
        (?<ipvfuture>
            \[
              v[0-9a-f]+ \. [a-z0-9\-\._\~!\$&\'()*+,;=:]+ # "v" 1*HEXDIG "." 1*( unreserved / sub-delims / ":" )
            \]
        ) # a future IP address
    ';

    /**
     * Hostname pattern based on RFC1034 (http://tools.ietf.org/html/rfc1034#section-3.5)
     * This pattern is the same as RFC952 (http://tools.ietf.org/html/rfc952)
     */
    const PATTERN_RFC1034_HOSTNAME = '
        (?<hostname>
            (?<hostnamelabel>[a-z]([a-z0-9\-]*[a-z0-9])?)(?:\.(?P>hostnamelabel))*
        )
    ';

    /**
     * Hostname pattern based on RFC2396 (http://tools.ietf.org/html/rfc2396#section-3.2.1).
     * This is the host specification used by the HTTP/1.1 RFC (http://tools.ietf.org/html/rfc2616)
     */
    const PATTERN_RFC2396_HOSTNAME = '
        (?<hostname>
            ([a-z0-9]([a-z0-9\-]*[a-z0-9])?\.)* # domainlabel
            ([a-z]([a-z0-9\-]*[a-z0-9])?) # toplabel
            \.?
        ) # *( domainlabel "." ) toplabel [ "." ]
    ';

    /**
     * RFC3986 registered name pattern (http://tools.ietf.org/html/rfc3986#section-3.2.2).
     * @remark This pattern is not exact since it requires at least one character for the hostname.
     */
    const PATTERN_RFC3986_HOSTNAME = '
        (?<hostname>
            ([a-z0-9\-\._\~!\$&\'()*+,;=]|%[0-9a-f]{2})+
        ) # *( unreserved / pct-encoded / sub-delims )
    ';

    /**
     * RFC3986 Scheme pattern (http://tools.ietf.org/html/rfc3986#section-3.1).
     */
    const PATTERN_RFC3986_SCHEME = '
      (?<scheme>[a-z][a-z0-9+-\.]*)
    ';

    /**
     * Userinfo pattern based on RFC3986 (http://tools.ietf.org/html/rfc3986#section-3.2.1)
     * @remark This pattern is the as for RFC2396 (http://tools.ietf.org/html/rfc2396#section-3.2.2).
     */
    const PATTERN_RFC3986_USERINFO = '
        (?:
          (?<userinfo>
           ([a-z0-9\-\._\~!\$&\'()*+,;=:]|%[0-9a-f]{2})*
          )@ # *( unreserved / pct-encoded / sub-delims / ":" )
        )?
    ';

    /**
     * Port pattern based on RFC3986 (http://tools.ietf.org/html/rfc3986#section-3.2.3).
     */
    const PATTERN_RFC3986_PORT = '
        (?:
            :(?<port>[0-9]*)
        )?
    ';

    /**
     * Path pattern based on RFC3986 (http://tools.ietf.org/html/rfc3986#section-3.3)
     */
    const PATTERN_RFC3986_PATH = '
        (?<pathabempty>
          (?:
            /(?<segment>([a-z0-9\-\._\~!\$&\'()*+,;=:@]|%[0-9a-f]{2})*) # *pchar
          )*
        ) # *( "/" segment )
    ';

    /**
     * Query pattern based on RFC3986 (http://tools.ietf.org/html/rfc3986#section-3.4)
     */
    const PATTERN_RFC3986_QUERY = '
        (?:
          \?
          (?<query>
            ([a-z0-9\-\._\~!\$&\'()*+,;=:@/?]|%[0-9a-f]{2})*
          ) # *( pchar / "/" / "?" )
        )?
    ';

    /**
     * Fragment pattern based on RFC3986 (http://tools.ietf.org/html/rfc3986#section-3.5)
     */
    const PATTERN_RFC3986_FRAGMENT = '
        (?:
          \#
          (?<fragment>
            ([a-z0-9\-\._\~!\$&\'()*+,;=:@/?]|%[0-9a-f]{2})*
          ) # *( pchar / "/" / "?" )
        )?
    ';

    const PATTERN = '~^
            (%s)://                                 # protocol
            (([\pL\pN-]+:)?([\pL\pN-]+)@)?          # basic auth
            (
                ([\pL\pN\pS-\.])+(\.?([\pL]|xn\-\-[\pL\pN-]+)+\.?) # a domain name
                    |                                              # or
                \d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}                 # a IP address
                    |                                              # or
                \[
                    (?:(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){6})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:::(?:(?:(?:[0-9a-f]{1,4})):){5})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){4})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,1}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){3})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,2}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){2})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,3}(?:(?:[0-9a-f]{1,4})))?::(?:(?:[0-9a-f]{1,4})):)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,4}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,5}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,6}(?:(?:[0-9a-f]{1,4})))?::))))
                \]  # a IPv6 address
            )
            (:[0-9]+)?                              # a port (optional)
            (/?|/\S+)                               # a /, nothing or a / with something
        $~ixu';

    /**
     * Host name pattern that IDNA hostnames (aka with utf-8 characters) used on RFC3986.
     * see https://tools.ietf.org/html/rfc5892#appendix-B.1
     * TODO make this less of a regex from hell!
     */
    const PATTERN_IDNA_HOSTNAME = '
        (?<hostname>
            (
                [-\._\~!\$&\'()*+,;=]
                |
                [\x{002D}\x{0030}-\x{0039}\x{0061}-\x{007A}\x{00DF}-\x{00F6}\x{00F8}-\x{00FF}\x{0101}\x{0103}\x{0105}\x{0107}\x{0109}\x{010B}\x{010D}\x{010F}\x{0111}\x{0113}\x{0115}\x{0117}\x{0119}\x{011B}\x{011D}\x{011F}\x{0121}\x{0123}\x{0125}\x{0127}\x{0129}\x{012B}\x{012D}\x{012F}\x{0131}\x{0135}\x{0137}-\x{0138}\x{013A}\x{013C}\x{013E}\x{0142}\x{0144}\x{0146}\x{0148}\x{014B}\x{014D}\x{014F}\x{0151}\x{0153}\x{0155}\x{0157}\x{0159}\x{015B}\x{015D}\x{015F}\x{0161}\x{0163}\x{0165}\x{0167}\x{0169}\x{016B}\x{016D}\x{016F}\x{0171}\x{0173}\x{0175}\x{0177}\x{017A}\x{017C}\x{017E}\x{0180}\x{0183}\x{0185}\x{0188}\x{018C}-\x{018D}\x{0192}\x{0195}\x{0199}-\x{019B}\x{019E}\x{01A1}\x{01A3}\x{01A5}\x{01A8}\x{01AA}-\x{01AB}\x{01AD}\x{01B0}\x{01B4}\x{01B6}\x{01B9}-\x{01BB}\x{01BD}-\x{01C3}\x{01CE}\x{01D0}\x{01D2}\x{01D4}\x{01D6}\x{01D8}\x{01DA}\x{01DC}-\x{01DD}\x{01DF}\x{01E1}\x{01E3}\x{01E5}\x{01E7}\x{01E9}\x{01EB}\x{01ED}\x{01EF}-\x{01F0}\x{01F5}\x{01F9}\x{01FB}\x{01FD}\x{01FF}\x{0201}\x{0203}\x{0205}\x{0207}\x{0209}\x{020B}\x{020D}\x{020F}\x{0211}\x{0213}\x{0215}\x{0217}\x{0219}\x{021B}\x{021D}\x{021F}\x{0221}\x{0223}\x{0225}\x{0227}\x{0229}\x{022B}\x{022D}\x{022F}\x{0231}\x{0233}-\x{0239}\x{023C}\x{023F}-\x{0240}\x{0242}\x{0247}\x{0249}\x{024B}\x{024D}\x{024F}-\x{02AF}\x{02B9}-\x{02C1}\x{02C6}-\x{02D1}\x{02EC}\x{02EE}\x{0300}-\x{033F}\x{0342}\x{0346}-\x{034E}\x{0350}-\x{036F}\x{0371}\x{0373}\x{0377}\x{037B}-\x{037D}\x{0390}\x{03AC}-\x{03CE}\x{03D7}\x{03D9}\x{03DB}\x{03DD}\x{03DF}\x{03E1}\x{03E3}\x{03E5}\x{03E7}\x{03E9}\x{03EB}\x{03ED}\x{03EF}\x{03F3}\x{03F8}\x{03FB}-\x{03FC}\x{0430}-\x{045F}\x{0461}\x{0463}\x{0465}\x{0467}\x{0469}\x{046B}\x{046D}\x{046F}\x{0471}\x{0473}\x{0475}\x{0477}\x{0479}\x{047B}\x{047D}\x{047F}\x{0481}\x{0483}-\x{0487}\x{048B}\x{048D}\x{048F}\x{0491}\x{0493}\x{0495}\x{0497}\x{0499}\x{049B}\x{049D}\x{049F}\x{04A1}\x{04A3}\x{04A5}\x{04A7}\x{04A9}\x{04AB}\x{04AD}\x{04AF}\x{04B1}\x{04B3}\x{04B5}\x{04B7}\x{04B9}\x{04BB}\x{04BD}\x{04BF}\x{04C2}\x{04C4}\x{04C6}\x{04C8}\x{04CA}\x{04CC}\x{04CE}-\x{04CF}\x{04D1}\x{04D3}\x{04D5}\x{04D7}\x{04D9}\x{04DB}\x{04DD}\x{04DF}\x{04E1}\x{04E3}\x{04E5}\x{04E7}\x{04E9}\x{04EB}\x{04ED}\x{04EF}\x{04F1}\x{04F3}\x{04F5}\x{04F7}\x{04F9}\x{04FB}\x{04FD}\x{04FF}\x{0501}\x{0503}\x{0505}\x{0507}\x{0509}\x{050B}\x{050D}\x{050F}\x{0511}\x{0513}\x{0515}\x{0517}\x{0519}\x{051B}\x{051D}\x{051F}\x{0521}\x{0523}\x{0525}\x{0559}\x{0561}-\x{0586}\x{0591}-\x{05BD}\x{05BF}\x{05C1}-\x{05C2}\x{05C4}-\x{05C5}\x{05C7}\x{05D0}-\x{05EA}\x{05F0}-\x{05F2}\x{0610}-\x{061A}\x{0621}-\x{063F}\x{0641}-\x{065E}\x{066E}-\x{0674}\x{0679}-\x{06D3}\x{06D5}-\x{06DC}\x{06DF}-\x{06E8}\x{06EA}-\x{06EF}\x{06FA}-\x{06FF}\x{0710}-\x{074A}\x{074D}-\x{07B1}\x{07C0}-\x{07F5}\x{0800}-\x{082D}\x{0900}-\x{0939}\x{093C}-\x{094E}\x{0950}-\x{0955}\x{0960}-\x{0963}\x{0966}-\x{096F}\x{0971}-\x{0972}\x{0979}-\x{097F}\x{0981}-\x{0983}\x{0985}-\x{098C}\x{098F}-\x{0990}\x{0993}-\x{09A8}\x{09AA}-\x{09B0}\x{09B2}\x{09B6}-\x{09B9}\x{09BC}-\x{09C4}\x{09C7}-\x{09C8}\x{09CB}-\x{09CE}\x{09D7}\x{09E0}-\x{09E3}\x{09E6}-\x{09F1}\x{0A01}-\x{0A03}\x{0A05}-\x{0A0A}\x{0A0F}-\x{0A10}\x{0A13}-\x{0A28}\x{0A2A}-\x{0A30}\x{0A32}\x{0A35}\x{0A38}-\x{0A39}\x{0A3C}\x{0A3E}-\x{0A42}\x{0A47}-\x{0A48}\x{0A4B}-\x{0A4D}\x{0A51}\x{0A5C}\x{0A66}-\x{0A75}\x{0A81}-\x{0A83}\x{0A85}-\x{0A8D}\x{0A8F}-\x{0A91}\x{0A93}-\x{0AA8}\x{0AAA}-\x{0AB0}\x{0AB2}-\x{0AB3}\x{0AB5}-\x{0AB9}\x{0ABC}-\x{0AC5}\x{0AC7}-\x{0AC9}\x{0ACB}-\x{0ACD}\x{0AD0}\x{0AE0}-\x{0AE3}\x{0AE6}-\x{0AEF}\x{0B01}-\x{0B03}\x{0B05}-\x{0B0C}\x{0B0F}-\x{0B10}\x{0B13}-\x{0B28}\x{0B2A}-\x{0B30}\x{0B32}-\x{0B33}\x{0B35}-\x{0B39}\x{0B3C}-\x{0B44}\x{0B47}-\x{0B48}\x{0B4B}-\x{0B4D}\x{0B56}-\x{0B57}\x{0B5F}-\x{0B63}\x{0B66}-\x{0B6F}\x{0B71}\x{0B82}-\x{0B83}\x{0B85}-\x{0B8A}\x{0B8E}-\x{0B90}\x{0B92}-\x{0B95}\x{0B99}-\x{0B9A}\x{0B9C}\x{0B9E}-\x{0B9F}\x{0BA3}-\x{0BA4}\x{0BA8}-\x{0BAA}\x{0BAE}-\x{0BB9}\x{0BBE}-\x{0BC2}\x{0BC6}-\x{0BC8}\x{0BCA}-\x{0BCD}\x{0BD0}\x{0BD7}\x{0BE6}-\x{0BEF}\x{0C01}-\x{0C03}\x{0C05}-\x{0C0C}\x{0C0E}-\x{0C10}\x{0C12}-\x{0C28}\x{0C2A}-\x{0C33}\x{0C35}-\x{0C39}\x{0C3D}-\x{0C44}\x{0C46}-\x{0C48}\x{0C4A}-\x{0C4D}\x{0C55}-\x{0C56}\x{0C58}-\x{0C59}\x{0C60}-\x{0C63}\x{0C66}-\x{0C6F}\x{0C82}-\x{0C83}\x{0C85}-\x{0C8C}\x{0C8E}-\x{0C90}\x{0C92}-\x{0CA8}\x{0CAA}-\x{0CB3}\x{0CB5}-\x{0CB9}\x{0CBC}-\x{0CC4}\x{0CC6}-\x{0CC8}\x{0CCA}-\x{0CCD}\x{0CD5}-\x{0CD6}\x{0CDE}\x{0CE0}-\x{0CE3}\x{0CE6}-\x{0CEF}\x{0D02}-\x{0D03}\x{0D05}-\x{0D0C}\x{0D0E}-\x{0D10}\x{0D12}-\x{0D28}\x{0D2A}-\x{0D39}\x{0D3D}-\x{0D44}\x{0D46}-\x{0D48}\x{0D4A}-\x{0D4D}\x{0D57}\x{0D60}-\x{0D63}\x{0D66}-\x{0D6F}\x{0D7A}-\x{0D7F}\x{0D82}-\x{0D83}\x{0D85}-\x{0D96}\x{0D9A}-\x{0DB1}\x{0DB3}-\x{0DBB}\x{0DBD}\x{0DC0}-\x{0DC6}\x{0DCA}\x{0DCF}-\x{0DD4}\x{0DD6}\x{0DD8}-\x{0DDF}\x{0DF2}-\x{0DF3}\x{0E01}-\x{0E32}\x{0E34}-\x{0E3A}\x{0E40}-\x{0E4E}\x{0E50}-\x{0E59}\x{0E81}-\x{0E82}\x{0E84}\x{0E87}-\x{0E88}\x{0E8A}\x{0E8D}\x{0E94}-\x{0E97}\x{0E99}-\x{0E9F}\x{0EA1}-\x{0EA3}\x{0EA5}\x{0EA7}\x{0EAA}-\x{0EAB}\x{0EAD}-\x{0EB2}\x{0EB4}-\x{0EB9}\x{0EBB}-\x{0EBD}\x{0EC0}-\x{0EC4}\x{0EC6}\x{0EC8}-\x{0ECD}\x{0ED0}-\x{0ED9}\x{0F00}\x{0F0B}\x{0F18}-\x{0F19}\x{0F20}-\x{0F29}\x{0F35}\x{0F37}\x{0F39}\x{0F3E}-\x{0F42}\x{0F44}-\x{0F47}\x{0F49}-\x{0F4C}\x{0F4E}-\x{0F51}\x{0F53}-\x{0F56}\x{0F58}-\x{0F5B}\x{0F5D}-\x{0F68}\x{0F6A}-\x{0F6C}\x{0F71}-\x{0F72}\x{0F74}\x{0F7A}-\x{0F80}\x{0F82}-\x{0F84}\x{0F86}-\x{0F8B}\x{0F90}-\x{0F92}\x{0F94}-\x{0F97}\x{0F99}-\x{0F9C}\x{0F9E}-\x{0FA1}\x{0FA3}-\x{0FA6}\x{0FA8}-\x{0FAB}\x{0FAD}-\x{0FB8}\x{0FBA}-\x{0FBC}\x{0FC6}\x{1000}-\x{1049}\x{1050}-\x{109D}\x{10D0}-\x{10FA}\x{1200}-\x{1248}\x{124A}-\x{124D}\x{1250}-\x{1256}\x{1258}\x{125A}-\x{125D}\x{1260}-\x{1288}\x{128A}-\x{128D}\x{1290}-\x{12B0}\x{12B2}-\x{12B5}\x{12B8}-\x{12BE}\x{12C0}\x{12C2}-\x{12C5}\x{12C8}-\x{12D6}\x{12D8}-\x{1310}\x{1312}-\x{1315}\x{1318}-\x{135A}\x{135F}\x{1380}-\x{138F}\x{13A0}-\x{13F4}\x{1401}-\x{166C}\x{166F}-\x{167F}\x{1681}-\x{169A}\x{16A0}-\x{16EA}\x{1700}-\x{170C}\x{170E}-\x{1714}\x{1720}-\x{1734}\x{1740}-\x{1753}\x{1760}-\x{176C}\x{176E}-\x{1770}\x{1772}-\x{1773}\x{1780}-\x{17B3}\x{17B6}-\x{17D3}\x{17D7}\x{17DC}-\x{17DD}\x{17E0}-\x{17E9}\x{1810}-\x{1819}\x{1820}-\x{1877}\x{1880}-\x{18AA}\x{18B0}-\x{18F5}\x{1900}-\x{191C}\x{1920}-\x{192B}\x{1930}-\x{193B}\x{1946}-\x{196D}\x{1970}-\x{1974}\x{1980}-\x{19AB}\x{19B0}-\x{19C9}\x{19D0}-\x{19DA}\x{1A00}-\x{1A1B}\x{1A20}-\x{1A5E}\x{1A60}-\x{1A7C}\x{1A7F}-\x{1A89}\x{1A90}-\x{1A99}\x{1AA7}\x{1B00}-\x{1B4B}\x{1B50}-\x{1B59}\x{1B6B}-\x{1B73}\x{1B80}-\x{1BAA}\x{1BAE}-\x{1BB9}\x{1C00}-\x{1C37}\x{1C40}-\x{1C49}\x{1C4D}-\x{1C7D}\x{1CD0}-\x{1CD2}\x{1CD4}-\x{1CF2}\x{1D00}-\x{1D2B}\x{1D2F}\x{1D3B}\x{1D4E}\x{1D6B}-\x{1D77}\x{1D79}-\x{1D9A}\x{1DC0}-\x{1DE6}\x{1DFD}-\x{1DFF}\x{1E01}\x{1E03}\x{1E05}\x{1E07}\x{1E09}\x{1E0B}\x{1E0D}\x{1E0F}\x{1E11}\x{1E13}\x{1E15}\x{1E17}\x{1E19}\x{1E1B}\x{1E1D}\x{1E1F}\x{1E21}\x{1E23}\x{1E25}\x{1E27}\x{1E29}\x{1E2B}\x{1E2D}\x{1E2F}\x{1E31}\x{1E33}\x{1E35}\x{1E37}\x{1E39}\x{1E3B}\x{1E3D}\x{1E3F}\x{1E41}\x{1E43}\x{1E45}\x{1E47}\x{1E49}\x{1E4B}\x{1E4D}\x{1E4F}\x{1E51}\x{1E53}\x{1E55}\x{1E57}\x{1E59}\x{1E5B}\x{1E5D}\x{1E5F}\x{1E61}\x{1E63}\x{1E65}\x{1E67}\x{1E69}\x{1E6B}\x{1E6D}\x{1E6F}\x{1E71}\x{1E73}\x{1E75}\x{1E77}\x{1E79}\x{1E7B}\x{1E7D}\x{1E7F}\x{1E81}\x{1E83}\x{1E85}\x{1E87}\x{1E89}\x{1E8B}\x{1E8D}\x{1E8F}\x{1E91}\x{1E93}\x{1E95}-\x{1E99}\x{1E9C}-\x{1E9D}\x{1E9F}\x{1EA1}\x{1EA3}\x{1EA5}\x{1EA7}\x{1EA9}\x{1EAB}\x{1EAD}\x{1EAF}\x{1EB1}\x{1EB3}\x{1EB5}\x{1EB7}\x{1EB9}\x{1EBB}\x{1EBD}\x{1EBF}\x{1EC1}\x{1EC3}\x{1EC5}\x{1EC7}\x{1EC9}\x{1ECB}\x{1ECD}\x{1ECF}\x{1ED1}\x{1ED3}\x{1ED5}\x{1ED7}\x{1ED9}\x{1EDB}\x{1EDD}\x{1EDF}\x{1EE1}\x{1EE3}\x{1EE5}\x{1EE7}\x{1EE9}\x{1EEB}\x{1EED}\x{1EEF}\x{1EF1}\x{1EF3}\x{1EF5}\x{1EF7}\x{1EF9}\x{1EFB}\x{1EFD}\x{1EFF}-\x{1F07}\x{1F10}-\x{1F15}\x{1F20}-\x{1F27}\x{1F30}-\x{1F37}\x{1F40}-\x{1F45}\x{1F50}-\x{1F57}\x{1F60}-\x{1F67}\x{1F70}\x{1F72}\x{1F74}\x{1F76}\x{1F78}\x{1F7A}\x{1F7C}\x{1FB0}-\x{1FB1}\x{1FB6}\x{1FC6}\x{1FD0}-\x{1FD2}\x{1FD6}-\x{1FD7}\x{1FE0}-\x{1FE2}\x{1FE4}-\x{1FE7}\x{1FF6}\x{214E}\x{2184}\x{2C30}-\x{2C5E}\x{2C61}\x{2C65}-\x{2C66}\x{2C68}\x{2C6A}\x{2C6C}\x{2C71}\x{2C73}-\x{2C74}\x{2C76}-\x{2C7B}\x{2C81}\x{2C83}\x{2C85}\x{2C87}\x{2C89}\x{2C8B}\x{2C8D}\x{2C8F}\x{2C91}\x{2C93}\x{2C95}\x{2C97}\x{2C99}\x{2C9B}\x{2C9D}\x{2C9F}\x{2CA1}\x{2CA3}\x{2CA5}\x{2CA7}\x{2CA9}\x{2CAB}\x{2CAD}\x{2CAF}\x{2CB1}\x{2CB3}\x{2CB5}\x{2CB7}\x{2CB9}\x{2CBB}\x{2CBD}\x{2CBF}\x{2CC1}\x{2CC3}\x{2CC5}\x{2CC7}\x{2CC9}\x{2CCB}\x{2CCD}\x{2CCF}\x{2CD1}\x{2CD3}\x{2CD5}\x{2CD7}\x{2CD9}\x{2CDB}\x{2CDD}\x{2CDF}\x{2CE1}\x{2CE3}-\x{2CE4}\x{2CEC}\x{2CEE}-\x{2CF1}\x{2D00}-\x{2D25}\x{2D30}-\x{2D65}\x{2D80}-\x{2D96}\x{2DA0}-\x{2DA6}\x{2DA8}-\x{2DAE}\x{2DB0}-\x{2DB6}\x{2DB8}-\x{2DBE}\x{2DC0}-\x{2DC6}\x{2DC8}-\x{2DCE}\x{2DD0}-\x{2DD6}\x{2DD8}-\x{2DDE}\x{2DE0}-\x{2DFF}\x{2E2F}\x{3005}-\x{3007}\x{302A}-\x{302D}\x{303C}\x{3041}-\x{3096}\x{3099}-\x{309A}\x{309D}-\x{309E}\x{30A1}-\x{30FA}\x{30FC}-\x{30FE}\x{3105}-\x{312D}\x{31A0}-\x{31B7}\x{31F0}-\x{31FF}\x{3400}-\x{4DB5}\x{4E00}-\x{9FCB}\x{A000}-\x{A48C}\x{A4D0}-\x{A4FD}\x{A500}-\x{A60C}\x{A610}-\x{A62B}\x{A641}\x{A643}\x{A645}\x{A647}\x{A649}\x{A64B}\x{A64D}\x{A64F}\x{A651}\x{A653}\x{A655}\x{A657}\x{A659}\x{A65B}\x{A65D}\x{A65F}\x{A663}\x{A665}\x{A667}\x{A669}\x{A66B}\x{A66D}-\x{A66F}\x{A67C}-\x{A67D}\x{A67F}\x{A681}\x{A683}\x{A685}\x{A687}\x{A689}\x{A68B}\x{A68D}\x{A68F}\x{A691}\x{A693}\x{A695}\x{A697}\x{A6A0}-\x{A6E5}\x{A6F0}-\x{A6F1}\x{A717}-\x{A71F}\x{A723}\x{A725}\x{A727}\x{A729}\x{A72B}\x{A72D}\x{A72F}-\x{A731}\x{A733}\x{A735}\x{A737}\x{A739}\x{A73B}\x{A73D}\x{A73F}\x{A741}\x{A743}\x{A745}\x{A747}\x{A749}\x{A74B}\x{A74D}\x{A74F}\x{A751}\x{A753}\x{A755}\x{A757}\x{A759}\x{A75B}\x{A75D}\x{A75F}\x{A761}\x{A763}\x{A765}\x{A767}\x{A769}\x{A76B}\x{A76D}\x{A76F}\x{A771}-\x{A778}\x{A77A}\x{A77C}\x{A77F}\x{A781}\x{A783}\x{A785}\x{A787}-\x{A788}\x{A78C}\x{A7FB}-\x{A827}\x{A840}-\x{A873}\x{A880}-\x{A8C4}\x{A8D0}-\x{A8D9}\x{A8E0}-\x{A8F7}\x{A8FB}\x{A900}-\x{A92D}\x{A930}-\x{A953}\x{A980}-\x{A9C0}\x{A9CF}-\x{A9D9}\x{AA00}-\x{AA36}\x{AA40}-\x{AA4D}\x{AA50}-\x{AA59}\x{AA60}-\x{AA76}\x{AA7A}-\x{AA7B}\x{AA80}-\x{AAC2}\x{AADB}-\x{AADD}\x{ABC0}-\x{ABEA}\x{ABEC}-\x{ABED}\x{ABF0}-\x{ABF9}\x{AC00}-\x{D7A3}\x{FA0E}-\x{FA0F}\x{FA11}\x{FA13}-\x{FA14}\x{FA1F}\x{FA21}\x{FA23}-\x{FA24}\x{FA27}-\x{FA29}\x{FB1E}\x{FE20}-\x{FE26}\x{FE73}\x{10000}-\x{1000B}\x{1000D}-\x{10026}\x{10028}-\x{1003A}\x{1003C}-\x{1003D}\x{1003F}-\x{1004D}\x{10050}-\x{1005D}\x{10080}-\x{100FA}\x{101FD}\x{10280}-\x{1029C}\x{102A0}-\x{102D0}\x{10300}-\x{1031E}\x{10330}-\x{10340}\x{10342}-\x{10349}\x{10380}-\x{1039D}\x{103A0}-\x{103C3}\x{103C8}-\x{103CF}\x{10428}-\x{1049D}\x{104A0}-\x{104A9}\x{10800}-\x{10805}\x{10808}\x{1080A}-\x{10835}\x{10837}-\x{10838}\x{1083C}\x{1083F}-\x{10855}\x{10900}-\x{10915}\x{10920}-\x{10939}\x{10A00}-\x{10A03}\x{10A05}-\x{10A06}\x{10A0C}-\x{10A13}\x{10A15}-\x{10A17}\x{10A19}-\x{10A33}\x{10A38}-\x{10A3A}\x{10A3F}\x{10A60}-\x{10A7C}\x{10B00}-\x{10B35}\x{10B40}-\x{10B55}\x{10B60}-\x{10B72}\x{10C00}-\x{10C48}\x{11080}-\x{110BA}\x{12000}-\x{1236E}\x{13000}-\x{1342E}\x{20000}-\x{2A6D6}\x{2A700}-\x{2B734}]
                |
                [\x{0378}-\x{0379}\x{037F}-\x{0383}\x{038B}\x{038D}\x{03A2}\x{0526}-\x{0530}\x{0557}-\x{0558}\x{0560}\x{0588}\x{058B}-\x{0590}\x{05C8}-\x{05CF}\x{05EB}-\x{05EF}\x{05F5}-\x{05FF}\x{0604}-\x{0605}\x{061C}-\x{061D}\x{0620}\x{065F}\x{070E}\x{074B}-\x{074C}\x{07B2}-\x{07BF}\x{07FB}-\x{07FF}\x{082E}-\x{082F}\x{083F}-\x{08FF}\x{093A}-\x{093B}\x{094F}\x{0956}-\x{0957}\x{0973}-\x{0978}\x{0980}\x{0984}\x{098D}-\x{098E}\x{0991}-\x{0992}\x{09A9}\x{09B1}\x{09B3}-\x{09B5}\x{09BA}-\x{09BB}\x{09C5}-\x{09C6}\x{09C9}-\x{09CA}\x{09CF}-\x{09D6}\x{09D8}-\x{09DB}\x{09DE}\x{09E4}-\x{09E5}\x{09FC}-\x{0A00}\x{0A04}\x{0A0B}-\x{0A0E}\x{0A11}-\x{0A12}\x{0A29}\x{0A31}\x{0A34}\x{0A37}\x{0A3A}-\x{0A3B}\x{0A3D}\x{0A43}-\x{0A46}\x{0A49}-\x{0A4A}\x{0A4E}-\x{0A50}\x{0A52}-\x{0A58}\x{0A5D}\x{0A5F}-\x{0A65}\x{0A76}-\x{0A80}\x{0A84}\x{0A8E}\x{0A92}\x{0AA9}\x{0AB1}\x{0AB4}\x{0ABA}-\x{0ABB}\x{0AC6}\x{0ACA}\x{0ACE}-\x{0ACF}\x{0AD1}-\x{0ADF}\x{0AE4}-\x{0AE5}\x{0AF0}\x{0AF2}-\x{0B00}\x{0B04}\x{0B0D}-\x{0B0E}\x{0B11}-\x{0B12}\x{0B29}\x{0B31}\x{0B34}\x{0B3A}-\x{0B3B}\x{0B45}-\x{0B46}\x{0B49}-\x{0B4A}\x{0B4E}-\x{0B55}\x{0B58}-\x{0B5B}\x{0B5E}\x{0B64}-\x{0B65}\x{0B72}-\x{0B81}\x{0B84}\x{0B8B}-\x{0B8D}\x{0B91}\x{0B96}-\x{0B98}\x{0B9B}\x{0B9D}\x{0BA0}-\x{0BA2}\x{0BA5}-\x{0BA7}\x{0BAB}-\x{0BAD}\x{0BBA}-\x{0BBD}\x{0BC3}-\x{0BC5}\x{0BC9}\x{0BCE}-\x{0BCF}\x{0BD1}-\x{0BD6}\x{0BD8}-\x{0BE5}\x{0BFB}-\x{0C00}\x{0C04}\x{0C0D}\x{0C11}\x{0C29}\x{0C34}\x{0C3A}-\x{0C3C}\x{0C45}\x{0C49}\x{0C4E}-\x{0C54}\x{0C57}\x{0C5A}-\x{0C5F}\x{0C64}-\x{0C65}\x{0C70}-\x{0C77}\x{0C80}-\x{0C81}\x{0C84}\x{0C8D}\x{0C91}\x{0CA9}\x{0CB4}\x{0CBA}-\x{0CBB}\x{0CC5}\x{0CC9}\x{0CCE}-\x{0CD4}\x{0CD7}-\x{0CDD}\x{0CDF}\x{0CE4}-\x{0CE5}\x{0CF0}\x{0CF3}-\x{0D01}\x{0D04}\x{0D0D}\x{0D11}\x{0D29}\x{0D3A}-\x{0D3C}\x{0D45}\x{0D49}\x{0D4E}-\x{0D56}\x{0D58}-\x{0D5F}\x{0D64}-\x{0D65}\x{0D76}-\x{0D78}\x{0D80}-\x{0D81}\x{0D84}\x{0D97}-\x{0D99}\x{0DB2}\x{0DBC}\x{0DBE}-\x{0DBF}\x{0DC7}-\x{0DC9}\x{0DCB}-\x{0DCE}\x{0DD5}\x{0DD7}\x{0DE0}-\x{0DF1}\x{0DF5}-\x{0E00}\x{0E3B}-\x{0E3E}\x{0E5C}-\x{0E80}\x{0E83}\x{0E85}-\x{0E86}\x{0E89}\x{0E8B}-\x{0E8C}\x{0E8E}-\x{0E93}\x{0E98}\x{0EA0}\x{0EA4}\x{0EA6}\x{0EA8}-\x{0EA9}\x{0EAC}\x{0EBA}\x{0EBE}-\x{0EBF}\x{0EC5}\x{0EC7}\x{0ECE}-\x{0ECF}\x{0EDA}-\x{0EDB}\x{0EDE}-\x{0EFF}\x{0F48}\x{0F6D}-\x{0F70}\x{0F8C}-\x{0F8F}\x{0F98}\x{0FBD}\x{0FCD}\x{0FD9}-\x{0FFF}\x{10C6}-\x{10CF}\x{10FD}-\x{10FF}\x{1249}\x{124E}-\x{124F}\x{1257}\x{1259}\x{125E}-\x{125F}\x{1289}\x{128E}-\x{128F}\x{12B1}\x{12B6}-\x{12B7}\x{12BF}\x{12C1}\x{12C6}-\x{12C7}\x{12D7}\x{1311}\x{1316}-\x{1317}\x{135B}-\x{135E}\x{137D}-\x{137F}\x{139A}-\x{139F}\x{13F5}-\x{13FF}\x{169D}-\x{169F}\x{16F1}-\x{16FF}\x{170D}\x{1715}-\x{171F}\x{1737}-\x{173F}\x{1754}-\x{175F}\x{176D}\x{1771}\x{1774}-\x{177F}\x{17DE}-\x{17DF}\x{17EA}-\x{17EF}\x{17FA}-\x{17FF}\x{180F}\x{181A}-\x{181F}\x{1878}-\x{187F}\x{18AB}-\x{18AF}\x{18F6}-\x{18FF}\x{191D}-\x{191F}\x{192C}-\x{192F}\x{193C}-\x{193F}\x{1941}-\x{1943}\x{196E}-\x{196F}\x{1975}-\x{197F}\x{19AC}-\x{19AF}\x{19CA}-\x{19CF}\x{19DB}-\x{19DD}\x{1A1C}-\x{1A1D}\x{1A5F}\x{1A7D}-\x{1A7E}\x{1A8A}-\x{1A8F}\x{1A9A}-\x{1A9F}\x{1AAE}-\x{1AFF}\x{1B4C}-\x{1B4F}\x{1B7D}-\x{1B7F}\x{1BAB}-\x{1BAD}\x{1BBA}-\x{1BFF}\x{1C38}-\x{1C3A}\x{1C4A}-\x{1C4C}\x{1C80}-\x{1CCF}\x{1CF3}-\x{1CFF}\x{1DE7}-\x{1DFC}\x{1F16}-\x{1F17}\x{1F1E}-\x{1F1F}\x{1F46}-\x{1F47}\x{1F4E}-\x{1F4F}\x{1F58}\x{1F5A}\x{1F5C}\x{1F5E}\x{1F7E}-\x{1F7F}\x{1FB5}\x{1FC5}\x{1FD4}-\x{1FD5}\x{1FDC}\x{1FF0}-\x{1FF1}\x{1FF5}\x{1FFF}\x{2065}-\x{2069}\x{2072}-\x{2073}\x{208F}\x{2095}-\x{209F}\x{20B9}-\x{20CF}\x{20F1}-\x{20FF}\x{218A}-\x{218F}\x{23E9}-\x{23FF}\x{2427}-\x{243F}\x{244B}-\x{245F}\x{26CE}\x{26E2}\x{26E4}-\x{26E7}\x{2700}\x{2705}\x{270A}-\x{270B}\x{2728}\x{274C}\x{274E}\x{2753}-\x{2755}\x{275F}-\x{2760}\x{2795}-\x{2797}\x{27B0}\x{27BF}\x{27CB}\x{27CD}-\x{27CF}\x{2B4D}-\x{2B4F}\x{2B5A}-\x{2BFF}\x{2C2F}\x{2C5F}\x{2CF2}-\x{2CF8}\x{2D26}-\x{2D2F}\x{2D66}-\x{2D6E}\x{2D70}-\x{2D7F}\x{2D97}-\x{2D9F}\x{2DA7}\x{2DAF}\x{2DB7}\x{2DBF}\x{2DC7}\x{2DCF}\x{2DD7}\x{2DDF}\x{2E32}-\x{2E7F}\x{2E9A}\x{2EF4}-\x{2EFF}\x{2FD6}-\x{2FEF}\x{2FFC}-\x{2FFF}\x{3040}\x{3097}-\x{3098}\x{3100}-\x{3104}\x{312E}-\x{3130}\x{318F}\x{31B8}-\x{31BF}\x{31E4}-\x{31EF}\x{321F}\x{32FF}\x{4DB6}-\x{4DBF}\x{9FCC}-\x{9FFF}\x{A48D}-\x{A48F}\x{A4C7}-\x{A4CF}\x{A62C}-\x{A63F}\x{A660}-\x{A661}\x{A674}-\x{A67B}\x{A698}-\x{A69F}\x{A6F8}-\x{A6FF}\x{A78D}-\x{A7FA}\x{A82C}-\x{A82F}\x{A83A}-\x{A83F}\x{A878}-\x{A87F}\x{A8C5}-\x{A8CD}\x{A8DA}-\x{A8DF}\x{A8FC}-\x{A8FF}\x{A954}-\x{A95E}\x{A97D}-\x{A97F}\x{A9CE}\x{A9DA}-\x{A9DD}\x{A9E0}-\x{A9FF}\x{AA37}-\x{AA3F}\x{AA4E}-\x{AA4F}\x{AA5A}-\x{AA5B}\x{AA7C}-\x{AA7F}\x{AAC3}-\x{AADA}\x{AAE0}-\x{ABBF}\x{ABEE}-\x{ABEF}\x{ABFA}-\x{ABFF}\x{D7A4}-\x{D7AF}\x{D7C7}-\x{D7CA}\x{D7FC}-\x{D7FF}\x{FA2E}-\x{FA2F}\x{FA6E}-\x{FA6F}\x{FADA}-\x{FAFF}\x{FB07}-\x{FB12}\x{FB18}-\x{FB1C}\x{FB37}\x{FB3D}\x{FB3F}\x{FB42}\x{FB45}\x{FBB2}-\x{FBD2}\x{FD40}-\x{FD4F}\x{FD90}-\x{FD91}\x{FDC8}-\x{FDCF}\x{FDFE}-\x{FDFF}\x{FE1A}-\x{FE1F}\x{FE27}-\x{FE2F}\x{FE53}\x{FE67}\x{FE6C}-\x{FE6F}\x{FE75}\x{FEFD}-\x{FEFE}\x{FF00}\x{FFBF}-\x{FFC1}\x{FFC8}-\x{FFC9}\x{FFD0}-\x{FFD1}\x{FFD8}-\x{FFD9}\x{FFDD}-\x{FFDF}\x{FFE7}\x{FFEF}-\x{FFF8}\x{1000C}\x{10027}\x{1003B}\x{1003E}\x{1004E}-\x{1004F}\x{1005E}-\x{1007F}\x{100FB}-\x{100FF}\x{10103}-\x{10106}\x{10134}-\x{10136}\x{1018B}-\x{1018F}\x{1019C}-\x{101CF}\x{101FE}-\x{1027F}\x{1029D}-\x{1029F}\x{102D1}-\x{102FF}\x{1031F}\x{10324}-\x{1032F}\x{1034B}-\x{1037F}\x{1039E}\x{103C4}-\x{103C7}\x{103D6}-\x{103FF}\x{1049E}-\x{1049F}\x{104AA}-\x{107FF}\x{10806}-\x{10807}\x{10809}\x{10836}\x{10839}-\x{1083B}\x{1083D}-\x{1083E}\x{10856}\x{10860}-\x{108FF}\x{1091C}-\x{1091E}\x{1093A}-\x{1093E}\x{10940}-\x{109FF}\x{10A04}\x{10A07}-\x{10A0B}\x{10A14}\x{10A18}\x{10A34}-\x{10A37}\x{10A3B}-\x{10A3E}\x{10A48}-\x{10A4F}\x{10A59}-\x{10A5F}\x{10A80}-\x{10AFF}\x{10B36}-\x{10B38}\x{10B56}-\x{10B57}\x{10B73}-\x{10B77}\x{10B80}-\x{10BFF}\x{10C49}-\x{10E5F}\x{10E7F}-\x{1107F}\x{110C2}-\x{11FFF}\x{1236F}-\x{123FF}\x{12463}-\x{1246F}\x{12474}-\x{12FFF}\x{1342F}-\x{1CFFF}\x{1D0F6}-\x{1D0FF}\x{1D127}-\x{1D128}\x{1D1DE}-\x{1D1FF}\x{1D246}-\x{1D2FF}\x{1D357}-\x{1D35F}\x{1D372}-\x{1D3FF}\x{1D455}\x{1D49D}\x{1D4A0}-\x{1D4A1}\x{1D4A3}-\x{1D4A4}\x{1D4A7}-\x{1D4A8}\x{1D4AD}\x{1D4BA}\x{1D4BC}\x{1D4C4}\x{1D506}\x{1D50B}-\x{1D50C}\x{1D515}\x{1D51D}\x{1D53A}\x{1D53F}\x{1D545}\x{1D547}-\x{1D549}\x{1D551}\x{1D6A6}-\x{1D6A7}\x{1D7CC}-\x{1D7CD}\x{1D800}-\x{1EFFF}\x{1F02C}-\x{1F02F}\x{1F094}-\x{1F0FF}\x{1F10B}-\x{1F10F}\x{1F12F}-\x{1F130}\x{1F132}-\x{1F13C}\x{1F13E}\x{1F140}-\x{1F141}\x{1F143}-\x{1F145}\x{1F147}-\x{1F149}\x{1F14F}-\x{1F156}\x{1F158}-\x{1F15E}\x{1F160}-\x{1F178}\x{1F17A}\x{1F17D}-\x{1F17E}\x{1F180}-\x{1F189}\x{1F18E}-\x{1F18F}\x{1F191}-\x{1F1FF}\x{1F201}-\x{1F20F}\x{1F232}-\x{1F23F}\x{1F249}-\x{1FFFD}\x{2A6D7}-\x{2A6FF}\x{2B735}-\x{2F7FF}\x{2FA1E}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E0000}\x{E0002}-\x{E001F}\x{E0080}-\x{E00FF}\x{E01F0}-\x{EFFFD}]
                |
                %[0-9a-f]{2}
            )+
        )
    ';

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Url) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Url');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        // Extract the main uri parts
        // Based on http://tools.ietf.org/html/rfc3986#appendix-B
        $uriPattern = $this->buildUriPattern($constraint->allowPath, $constraint->allowQuery, $constraint->allowFragment);
        if (!preg_match($uriPattern, $value, $parts)) {
            $this->addViolation($value, $constraint);

            return;
        }

        // Check the scheme
        if (!in_array(strtolower($parts['scheme']), array_map('strtolower', $constraint->protocols), true)) {
            $this->addViolation($value, $constraint);

            return;
        }

        // Check the authority
        $authorityPattern = $this->buildAuthorityPattern($constraint->hostTypes, $constraint->allowUserInfo, $constraint->allowPort);
        if (!preg_match($authorityPattern, $parts['authority'])) {
            $this->addViolation($value, $constraint);
        }
    }

    private function addViolation($value, Constraint $constraint)
    {
        $this->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->addViolation();
    }

    protected function getHostType($type)
    {
        switch (strtolower($type)) {
            case 'ipv4':
                return self::PATTERN_HOST_IP_V4;
                break;
            case 'ipv6':
                return self::PATTERN_HOST_IP_V6;
                break;
            case 'ipfuture':
                return self::PATTERN_HOST_IP_FUTURE;
                break;
            case 'rfc2396':
                return self::PATTERN_RFC2396_HOSTNAME;
                break;
            case 'rfc1034':
                return self::PATTERN_RFC1034_HOSTNAME;
            case 'rfc3986':
                return self::PATTERN_RFC3986_HOSTNAME;
            case 'idna':
                return self::PATTERN_IDNA_HOSTNAME;
        }

        return null;
    }

    private function buildAuthorityPattern(array $hostTypes = array(), $userInfo = true, $port = true)
    {
        if ($userInfo) {
            $parts[] = self::PATTERN_RFC3986_USERINFO;
        }

        $hostPattern = array();
        foreach ($hostTypes as $type) {
            $pattern = $this->getHostType($type);
            if (!is_string($pattern)) {
                throw new ConstraintDefinitionException('Unknown %s found in hostTypes.');
            }
            $hostPattern[] = $pattern;
        }

        return
            '~^'.
            ($userInfo ? self::PATTERN_RFC3986_USERINFO : '').
            '(?<host>'.implode('|', $hostPattern).')'.
            ($port ? self::PATTERN_RFC3986_PORT : '').
            '$~iux'
            ;
    }

    private function buildUriPattern($allowPath, $allowQuery, $allowFragment)
    {
        return '~'.
            self::PATTERN_RFC3986_SCHEME.
            '://(?<authority>[^/?#]+)'.
            ($allowPath ? self::PATTERN_RFC3986_PATH : '').
            ($allowQuery ? self::PATTERN_RFC3986_QUERY : '').
            ($allowFragment ? self::PATTERN_RFC3986_FRAGMENT : '').
            '$~iux'
        ;
    }
}
