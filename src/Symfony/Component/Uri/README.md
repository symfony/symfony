Uri Component
=============

The Uri component is a low-level Symfony components that enhances PHP built-in
features. The primary goal is to have a consistent and object-oriented approach
for `parse_url()` and `parse_str()` functions.

Getting Started
---------------

```bash
composer require symfony/uri
```

Usage
-----

```php
use Symfony\Component\Uri\QueryString;
use Symfony\Component\Uri\Uri;

require 'vendor/autoload.php';

$uri = Uri::parse('https://example.com/foo/bar?baz=qux&arr[key]=foo&arr[another]=bar');
$uri = $uri->withFragmentTextDirective('start', 'end', 'prefix', 'suffix');

echo (string) $uri."\n"; // https://example.com/foo/bar#:~:text=prefix-,start,end,-suffix

$queryString = $uri->query;
$baz = $queryString->get('baz'); // 'qux'
$arr = $queryString->get('arr'); // ['key' => 'foo', 'another' => 'bar']

// Uri decodes the authority part of the URI
$uri = Uri::parse('https://user:p%40ss@host:123/path?query#fragment');
echo $uri->password."\n"; // 'p@ss'

// QueryString makes a difference between '.' and '_'
$queryString = QueryString::parse('foo.bar=1&foo_bar=2');
echo $queryString->get('foo.bar')."\n"; // '1'
echo $queryString->get('foo_bar')."\n"; // '2'
```

Notable Differences With PHP Functions
--------------------------------------

### `parse_url()`

 * `parse_url()` **does not** decode the auth component of the URL (user and
   pass). This makes it impossible to use the `parse_url()` function to parse
   a URL with a username or password that contains a colon (`:`) or
   an `@` character.

### `parse_str()`

 * `parse_str()` overwrites any duplicate field in the query parameter
   (e.g. `?foo=bar&foo=baz` will return `['foo' => 'baz']`). `foo` should be an
   array instead with the two values.
 * `parse_str()` replaces `.` in the query parameter keys with `_`, thus no
   distinction can be done between `foo.bar` and `foo_bar`.
 * `parse_str()` doesn't "support" `+` in the parameter keys and replaces them
  with `_` instead of a space.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
