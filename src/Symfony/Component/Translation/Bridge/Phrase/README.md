Phrase Translation Provider
============================

Provides Phrase integration for Symfony Translation.

DSN example
-----------

```
// .env file
PHRASE_DSN=phrase://PROJECT_ID:API_TOKEN@default?userAgent=myProject
```

**DSN elements**

 - `PROJECT_ID`: can be retrieved in Phrase from `project settings > API > Project ID`
 - `API_TOKEN`: can be created in your [Phrase profile settings](https://app.phrase.com/settings/oauth_access_tokens)
 - `default`: endpoint, defaults to `api.phrase.com`

**Required DSN query parameters**

 - `userAgent`: please read [this](https://developers.phrase.com/api/#overview--identification-via-user-agent) for some examples.

See [fine tuning your Phrase api calls](#fine-tuning-your-phrase-api-calls) for additional DSN options.

Phrase locale names
-------------------

Translations being imported using the Symfony XLIFF format in Phrase, locales are matched on locale name in Phrase.
Therefore it's necessary the locale names should be as defined in [RFC4646](https://www.ietf.org/rfc/rfc4646.txt) (e.g. pt-BR rather than pt_BR).
Not doing so will result in Phrase creating a new locale for the imported keys.

Locale creation
---------------

If you define a locale in your `translation.yaml` which is not configured in your Phrase project, it will be automatically created. Deletion of locales however, is (currently) not managed by this provider.

Domains as tags
---------------

Translations will be tagged in Phrase with the Symfony translation domain they belong to.
Check the [wickedone/phrase-translation-bundle](https://github.com/wickedOne/phrase-translation-bundle) if you need help managing your tags in Phrase.

Cache
-----

The read responses from Phrase are cached to speed up the read and delete methods of this provider and also to contribute to the rate limit as little as possible.
Therefore the factory should be initialised with a PSR-6 compatible cache adapter.

Fine tuning your Phrase api calls
---------------------------------

You can fine tune the read and write methods of this provider by adding query parameters to your dsn configuration.
General usage is `read|write[option_name]=value`

**example:
** `phrase://PROJECT_ID:API_TOKEN@default?read[encoding]=UTF-8&write[update_descriptions]=0`

**Read**

In order to read translations from Phrase the [download locale](https://developers.phrase.com/api/#get-/projects/-project_id-/locales/-id-/download) call is made to the Phrase API, supported parameters can be found in their documentation.

One additional read parameter is `fallback_locale_enabled` (defaults to `0`). When set to `1`, this provider will use the fallback locales as they are configured in Phrase.

> ❗enabling the fallback locale will disable the caching of the conditional get requests

**Write**

In order to write translations to Phrase the [upload](https://developers.phrase.com/api/#post-/projects/-project_id-/uploads) call is made to the Phrase API, supported parameters can be found in their documentation.

**Default values**
This provider uses the following default values for read and write requests. All but `file_format` and `tags` can be overridden by configuring your DSN query parameters.

| method(s)    | name                         |  type  | default value                                 |
|--------------|------------------------------|:------:|-----------------------------------------------|
| read & write | `file_format`                | string | symfony_xliff                                 |
| read & write | `tags`                       | string | dynamically set to symfony translation domain |
| read         | `include_empty_translations` |  bool  | 1                                             |
| read         | `format_options`             | array  | enclose_in_cdata                              |
| read         | `fallback_locale_enabled`    |  bool  | 0                                             |
| write        | `update_translations`        |  bool  | 1                                             |

Resources
---------

 * [Phrase strings API documentation](https://developers.phrase.com/api/#overview)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
