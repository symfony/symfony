# DSN documentation

>>> This file will be moved to symfony-docs <<<

A DSN is a string used to configure many services. A common DSN may look like a
URL, other look like a file path.

```text
memcached://127.0.0.1
mysql://user:password@127.0.0.1:3306/my_table
memcached:///var/local/run/memcached.socket?weight=25
```

Both types can have parameters, user, password.

## DSN Functions

A DSN may contain zero or more functions. The DSN parser supports a function syntax
but not functionality itself. The function arguments must be separated with space
or comma. Here are some example functions.

```
failover(dummy://a dummy://a)
failover(dummy://a,dummy://a)
failover:(dummy://a,dummy://a)
roundrobin(dummy://a failover(dummy://b dummy://a) dummy://b)
```

## Parsing

There are two methods for parsing; `DsnParser::parse()` and `DsnParser::parseSimple()`.
The latter is useful in situations where DSN functions are not needed.

```php
$dsn = DsnParser::parseSimple('scheme://127.0.0.1/foo/bar?key=value');
echo get_class($dsn); // "Symfony\Component\Dsn\Configuration\Url"
echo $dsn->getHost(); // "127.0.0.1"
echo $dsn->getPath(); // "/foo/bar"
echo $dsn->getPort(); // null
```

If functions are supported (like in the Mailer component) we can use `DsnParser::parse()`:

```php
$func = DsnParser::parse('failover(sendgrid://KEY@default smtp://127.0.0.1)');
echo $func->getName(); // "failover"
echo get_class($func->first()); // "Symfony\Component\Dsn\Configuration\Url"
echo $func->first()->getHost(); // "default"
echo $func->first()->getUser(); // "KEY"
```

```php

$func = DsnParser::parse('foo(udp://localhost failover:(tcp://localhost:61616,tcp://remotehost:61616)?initialReconnectDelay=100)?start=now');
echo $func->getName(); // "foo"
echo $func->getParameters()['start']; // "now"
$args = $func->getArguments();
echo get_class($args[0]); // "Symfony\Component\Dsn\Configuration\Url"
echo $args[0]->getScheme(); // "udp"
echo $args[0]->getHost(); // "localhost"

echo get_class($args[1]); // "Symfony\Component\Dsn\Configuration\DsnFunction"
```

When using `DsnParser::parse()` on a string that does not contain any DSN functions,
the parser will automatically add a default "dsn" function. This is added to provide
a consistent return type of the method.

The string `redis://127.0.0.1` will automatically be converted to `dsn(redis://127.0.0.1)`
when using `DsnParser::parse()`.

```php
$func = DsnParser::parse('smtp://127.0.0.1');
echo $func->getName(); // "dsn"
echo get_class($func->first()); // "Symfony\Component\Dsn\Configuration\Url"
echo $func->first()->getHost(); // "127.0.0.1"


$func = DsnParser::parse('dsn(smtp://127.0.0.1)');
echo $func->getName(); // "dsn"
echo get_class($func->first()); // "Symfony\Component\Dsn\Configuration\Url"
echo $func->first()->getHost(); // "127.0.0.1"
```

## Consuming

The result of parsing a DSN string is a `DsnFunction` or `Dsn`. A `DsnFunction` has
a `name`, `argument` and may have `parameters`. An argument is either a `DsnFunction`
or a `Dsn`.

A `Dsn` could be a `Path` or `Url`. All 3 objects has methods for getting parts of
the DSN string.

- `getScheme()`
- `getUser()`
- `getPassword()`
- `getHost()`
- `getPort()`
- `getPath()`
- `getParameters()`

## Not supported

### Smart merging of options

The current DSN is valid, but it is up to the consumer to make sure both host1 and
host2 has `global_option`.

```
redis://(host1:1234,host2:1234?node2_option=a)?global_option=b
```

### Special DSN

The following DSN syntax are not supported.

```
// Rust
pgsql://user:pass@tcp(localhost:5555)/dbname

// Java
jdbc:informix-sqli://<server>[:<port>]/<databaseName>:informixserver=<dbservername>

```

We do not support DSN strings for OCDB connections like:

```
Driver={ODBC Driver 13 for SQL Server};server=localhost;database=WideWorldImporters;trusted_connection=Yes;
```

However, we do support "only parameters":

```
ocdb://?Driver=ODBC+Driver+13+for+SQL+Server&server=localhost&database=WideWorldImporters&trusted_connection=Yes
```


## Definition

There is no official DSN RFC. Symfony has defined a DSN configuration string as
using the following definition. The "URL looking" parts of a DSN is based from
[RFC 3986](https://tools.ietf.org/html/rfc3986).


```
configuration:
  { function | dsn }

function:
  function_name[:](configuration[,configuration])[?query]

function_name:
  REGEX: [a-zA-Z0-9\+-]+

dsn:
  { scheme:[//]authority[path][?query] | scheme:[//][userinfo]path[?query] | host:port[path][?query] }

scheme:
  REGEX: [a-zA-Z0-9\+-\.]+

authority:
  [userinfo@]host[:port]

userinfo:
  { user[:password] | :password }

path:
  "Normal" URL path according to RFC3986 section 3.3.
  REGEX: (/? | (/[a-zA-Z0-9-\._~%!\$&'\(\}\*\+,;=:@]+)+)

query:
  "Normal" URL query according to RFC3986 section 3.4.
  REGEX: [a-zA-Z0-9-\._~%!\$&'\(\}\*\+,;=:@]+

user:
  This value can be URL encoded.
  REGEX: [a-zA-Z0-9-\._~%!\$&'\(\}\*\+,;=]+

password:
  This value can be URL encoded.
  REGEX: [a-zA-Z0-9-\._~%!\$&'\(\}\*\+,;=]+

host:
  REGEX: [a-zA-Z0-9-\._~%!\$&'\(\}\*\+,;=]+

post:
  REGEX: [0-9]+

```

Example of formats that are supported:

- scheme://127.0.0.1/foo/bar?key=value
- scheme://user:pass@127.0.0.1/foo/bar?key=value
- scheme:///var/local/run/memcached.socket?weight=25
- scheme://user:pass@/var/local/run/memcached.socket?weight=25
- scheme:?host[localhost]&host[localhost:12345]=3
- scheme://a
- scheme://
- server:80
