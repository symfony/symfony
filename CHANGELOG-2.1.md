CHANGELOG for 2.1.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.1 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.1.0...v2.1.1

* 2.1.6 (2012-12-21)

 * b8e5689: [FrameworkBundle] fixed ESI calls
 * ce536cd: [FrameworkBundle] fixed ESI calls

* 2.1.5 (2012-12-20)

 * 532cc9a: [FrameworkBundle] added support for URIs as an argument to HttpKernel::render()
 * 1f8c501: [FrameworkBundle] restricted the type of controllers that can be executed by InternalController
 * 2cd43da: [Process] Allow non-blocking start with PhpProcess
 * 8b2c17f: fix double-decoding in the routing system
 * 098b593: [Session] Added exception to save method
 * ad29df5: [Form] Fixed DateTimeToStringTransformer parsing on PHP < 5.3.8
 * 773d818: [FrameworkBundle] Added a check on file mime type for CodeHelper::fileExcerpt()
 * f24e3d7: [HttpKernel] Revise MongoDbProfilerStorage::write() return value
 * 78c5273: [Session] Document Mongo|MongoClient argument type instead of "object"
 * de19a81: [HttpKernel] Support MongoClient and Mongo connection classes
 * b28af77: [Session] Support MongoClient and Mongo connection classes
 * 20e93bf: [Session] Utilize MongoDB::selectCollection()
 * b20c5ca: [Form] Fixed reverse transformation of values in DateTimeToStringTransformer
 * d2231d8: [Console] Add support for parsing terminal width/height on localized windows, fixes #5742
 * 03b880f: [Form] Fixed treatment of countables and traversables in Form::isEmpty()
 * 21a59ca: [Form] Fixed FileType not to throw an exception when bound empty
 * eac14b5: Check if key # is defined in $value
 * a0e2391: [FrameworkBundle] used the new method for trusted proxies
 * d6a402a: [Security] fixed path info encoding (closes #6040, closes #5695)
 * 47dfb9c: [HttpFoundation] added some tests for the previous merge and removed dead code (closes #6037)
 * 1ab4923: Improved Cache-Control header when no-cache is sent
 * 4e909bd: Fix to allow null values in labels array
 * 9e46819: Fixed: HeaderBag::parseCacheControl() not parsing quoted zero correctly
 * 8bb3208: [Config] Loader::import must return imported data
 * ca5d9ac: [DoctrineBridge] Fixed caching in DoctrineType when "choices" or "preferred_choices" is passed
 * 6e7e08f: [Form] Fixed the default value of "format" in DateType to DateType::DEFAULT_FORMAT if "widget" is not "single_text"
 * 447ff91: [HttpFoundation] changed UploadedFile::move() to use move_uploaded_file() when possible (closes #5878, closes #6185)
 * 0489799: [HttpFoundation] added a check for the host header value
 * b604eb7: [DoctrineBridge] Improved performance of the EntityType when used with the "query_builder" option
 * 99321cb: [DoctrineBridge] Fixed: Exception is thrown if the entity class is not known to Doctrine
 * 2ed30e7: Fixed DefaultValue for session.auto_start in NodeDefinition
 * ae3d531: [TwigBundle] Moved the registration of the app global to the environment

* 2.1.4 (2012-11-29)

 * e5536f0: replaced magic strings by proper constants
 * 6a3ba52: fixed the logic in Request::isSecure() (if the information comes from a source that we trust, don't check other ones)
 * 67e12f3: added a way to configure the X-Forwarded-XXX header names and a way to disable trusting them
 * b45873a: fixed algorithm used to determine the trusted client IP
 * 254b110: removed the non-standard Client-IP HTTP header
 * 06ee53b: [Form] improve error message with a "hasser" hint for PropertyAccessDeniedException
 * ac77c5b: [Form] Updated checks for the ICU version from 4.5+ to 4.7+ due to test failures with ICU 4.6
 * 2fe04e1: Update src/Symfony/Component/Form/Extension/Core/Type/FileType.php
 * bbeff54: Xliff with other node than source or target are ignored
 * 29bfa13: small fix of #5984 when the container param is not set
 * f211b19: Filesystem Component mirror symlinked directory fix
 * 64b54dc: Use better default ports in urlRedirectAction
 * e7401a2: Update src/Symfony/Component/DomCrawler/Tests/FormTest.php
 * b0e468f: Update src/Symfony/Component/DomCrawler/Form.php
 * 1daefa5: [Routing] made it compatible with older PCRE version (pre 8)
 * f2cbea3: [Security] remove escape charters from username provided by Digest DigestAuthenticationListener
 * 82334d2: Force loader to be null or a EntityLoaderInterface
 * 694697d: [Security] Fixed digest authentication
 * c067586: [Security] Fixed digest authentication
 * d2920c9: Added HttpCache\Store::generateContentDigest() + changed visibility
 * e12bd12: [HttpFoundation] Make host & methods really case insensitive in the RequestMacther
 * c659e78: Make YamlFileLoader and XmlFileLoader file loading extensible
 * 0f75586: [Form] Removed an exception that prevented valid formats from being passed, e.g. "h" for the hour, "L" for the month etc.
 * 84b760b: [HttpKernel] fixed Client when using StreamedResponses (closes #5370)
 * 67e697f: fixed PDO session handler for Oracle (closes #5829)
 * c2a8a0b: [HttpFoundation] fixed PDO session handler for Oracle (closes #5829)
 * a30383d: [Locale] removed a check that is done too early (and it is done twice anyways)
 * 84635bd: [Form] allowed no type guesser to be registered
 * 8377146: Adding new localized strings for farsi validation.
 * e34fb41: [HttpFoundation] moved the HTTP protocol check from StreamedResponse to Response (closes #5937)
 * 4909bc3: [Form] Fixed forms not to be marked invalid if their children are already marked invalid
 * dc80385: [Form] Fixed NumberToLocalizedStringTransformer to accept both comma and dot as decimal separator, if possible
 * 208e134: [FrameworkBundle] Router skip defaults resolution for arrays
 * a0af8bf: [Form] Adapted HTML5 format in DateTimeType as response to a closed ICU ticket
 * 6b42c8c: The exception message should say which field is not mapped
 * 9872d26: [HttpFoundation] Fix name sanitization after perfoming move
 * 2d9a6fc: Use Norm Data instead of Data
 * a094f7e: Add check to Store::unlock to ensure file exists

* 2.1.3 (2012-10-30)

 * 6f15c47: [ClassLoader] fixed unbracketed namespaces (closes #5747)
 * 20898e5: Add to DateFormats 'D M d H:i:s Y T' (closes #5830)
 * b844d6b: [Form] Fixed DoctrineOrmTypeGuesser to guess the "required" option for to-one associations
 * 965734e: fixed fallback locale
 * bda29b3: [Form] Fixed error message in PropertyPath to not advice to use a non-existing feature
 * bf3e358: [Form] Fixed creation of multiple money fields with different currencies
 * 8f81f07: [Form] Fixed setting the "data" option to an object in "choice" and "entity" type
 * 53c43bf: Fixed Serbian plural translations.
 * 959c1df: Fixed IPv6 Check in RequestMatcher
 * cf1e02d: [Console] Fix error when mode is not in PATH
 * 6b66bc3: [2.1] Added missing error return codes in commands
 * e0a3fc1: Made the router lazy when setting the context
 * 89f7b5e: [HttpFoundation] fixed empty path when using Request::create() (closes #5729)
 * 8c6b7a4: Fixed the handling of the intl locale when setting the default locale
 * 673f74b: [HttpFoundation] Fixed #5697 - Request::createFromGlobals, Request::getContentType Changed checking CONTENT_TYPE from server to headers variable
 * 1566f9f: [Routing] fix handling of whitespace and synch between collection prefix and route pattern
 * b439d13: fixed DomCrwaler/Form to handle <button> when submitted
 * a4f3ea9: [2.1][DependencyInjection] Incomplete error handling in the container
 * 90145d2: [Routing] fix handling of two starting slashes in the pattern
 * cf422bf: [Validator] Updated swedish translation
 * 132ba25: Update src/Symfony/Component/Validator/Resources/translations/validators.de.xlf
 * 6a6b4ae: Updated lithuanian validation translation
 * 74d10d6: [DomCrawler] Allows using multiselect through Form::setValues().
 * a6ae6f6: [Translation] forced the catalogue to be regenerated when a resource is added (closes symfony/Translation#1)
 * 2568432: [Form] Hardened code of ViolationMapper against errors
 * 6c59fbd: [HttpFoundation] Fixed #5611 - Request::splitHttpAcceptHeader incorrect result order.
 * 2d41229: [Form] Fixed negative index access in PropertyPathBuilder
 * ed1cf54: Update src/Symfony/Component/Validator/Resources/translations/validators.ro.xlf
 * 47d7531: [2.1] Fix SessionHandlerInterface autoloading
 * 1a53b12: [2.0][http-foundation] Fix Response::getDate method
 * 3cc3c67: [DoctrineBridge] Require class option for DoctrineType
 * 4e3ea22: [HttpFoundation] fixed the path to the SensioHandlerInterface class in composer.json
 * 7444cb9: Support the new Microsoft URL Rewrite Module for IIS 7.0
 * c120c4d: Added Base64 encoding, decoding to MongoDBProfilerStorage
 * 335aa86: Update src/Symfony/Component/Validator/Resources/translations/validators.pl.xlf
 * 27b2df9: [Process] Fixed bug introduced by 7bafc69f38a3512eb15aad506959a4e7be162e52.
 * d7623ae: [DomCrawler] Added test for supported encodings by mbstring
 * c812b9d: [Config] Fixed preserving keys in associative arrays
 * c869a65: [Console] Fixed return value for Command::run
 * 2ceebdc: fixed stringification of array objects in RequestDataCollector (closes #5295)
 * b8a2f8c: [HttpFoundation] removed the username and password from generated URL as generated by the Request class (closes #5555)
 * c4429af: [Console] fixed default argument display (closes #5563)

* 2.1.2 (2012-09-20)

 * 7bafc69: Add a Sigchild compatibility mode (set to false by default)
 * 8dd19d8: fix Fatal error: Cannot access private property
 * 3269014: Added Bulgarian translation
 * de6658b: [Profiler]Use the abstract method to get client IP

* 2.1.1 (2012-09-11)

 * fix Composer configuration
