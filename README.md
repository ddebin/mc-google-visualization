![Build Status](https://github.com/ddebin/mc-google-visualization/workflows/CI/badge.svg)

# MC_Google_Visualization: Google Visualization datasource with your own database

MC_Google_Visualization provides simple support for integrating [Google Visualization charts and graphs](http://code.google.com/apis/visualization/documentation/) with your own internal database.
It includes a complete parser for the [Google Visualization Query Language]((http://code.google.com/apis/visualization/documentation/querylanguage.html)), giving you the same ease of pivoting and formatting data from
your database as is currently possible with Google Spreadsheets.

It's a fork of https://code.google.com/p/mc-goog-visualization/.

## Installing

Install via [Composer](https://getcomposer.org/):

```bash
composer require ddebin/mc-google-visualization
```

## Examples

Some examples can be found in the `examples/` directory. Browse to `examples/` to see the list. For these examples, PDO with SQLite3 support is required.

```bash
cd examples/
php -S localhost:8000
```

And then browse to http://localhost:8000/.

You must allow Flash content in local (cf. [*Note for Developers*](https://developers.google.com/chart/interactive/docs/gallery/motionchart)).

## Differences Between MC_Google_Visualization and Reference Query Language

MC_Google_Visualization tries to be exactly compatible with the query language [defined by Google](http://code.google.com/apis/visualization/documentation/querylanguage.html),
but writing this in PHP makes some choices easier than others.

Here's where there are still known incompatibilities between our implementations:

### Format Strings

The Google Visualization Query Language defines their formats as patterns supported by [ICU](http://www.icu-project.org/).
Since PHP has no built-in support for these patterns, we instead use the default patterns provided by PHP. For "date", "timeofday", and
"datetime" fields, we use PHP `date()` formatting strings.

For number fields, we use a custom set of patterns that match the most common formatting styles. A format string of "num:x" runs the number
through `number_format` and shows `x` decimal places. The special format string "dollars" will prepend the string with a dollar sign and format
the number to two decimal places. The format string "percent" will multiply the number by 100, format it to one decimal place, and append a percent sign.
Anything else will be treated as a `sprintf()` format string.

## More Information

 * [Google Visualization Query Language Reference](http://code.google.com/apis/visualization/documentation/querylanguage.html)
 * [PHP PDO Reference](http://www.php.net/pdo)
