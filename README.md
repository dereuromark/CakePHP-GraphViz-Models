# CakePHP ModelGraph plugin

[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%205.4-8892BF.svg)](https://php.net/)
[![Coding Standards](https://img.shields.io/badge/cs-PSR--2--R-yellow.svg)](https://github.com/php-fig-rectified/fig-rectified-standards)

This is a CakePHP shell that will find all Tables in your CakePHP application and
plugins, figure out the relationships between them, and will build a nice graph,
visualizing those relationships for you.

It supports CakePHP 3.x, and requires PHP 5.4+ or greater. Windows is also supporting.

## Requirements

This script relies on phpDocumentor/Graphviz
package, rather than directly on the command-line dot tool.
But you will need to install the Graphviz command line tool incl. `dot`.

If on Windows, make sure you set the path in Configure key `GraphViz.path`:
```php
// config/app.php
'GraphViz' => [
	'path' => 'C:\...\graphviz\bin\\',
],
```


## Installation

```
require-dev: {
	"mamchenkov/cakephp-graphviz-models": "dev-develop"
}
```

Currently you need also:
```
"repositories" : [
	{
		"type": "vcs",
		"url": "https://github.com/dereuromark/CakePHP-GraphViz-Models"
	},
]
```

Load plugin in `config/bootstrap.php`

```php
Plugin::load('ModelGraph');
```


## Usage

The simplest way to use this shell is just to run it via CakePHP console:

```
$ bin/cake ModelGraph generate
```

This should generate a graph.png image in your TMP directory.  Please have a look.

If you need more control, there are two options that this shell understand from the
command line: filename and format.   You can use either the filename option like so:

```
$ bin/cake ModelGraph generate /tmp/relations.dot
```
It will derive the format from the extension if possible.

You can provide the format manually, as well:

```
$ bin/cake ModelGraph generate -f svg
```

No special magic is done about the filename.  What You Give Is What You Get.  As for the
format, you can use anything that GraphViz supports and understands.

If you are still looking for more control, have a look inside the script.  There are
plenty of settings, options, parameters, and comments for you to make sense of it all. It
might be helpful to get familiar with GraphViz Dot Language, just to feel a tiny bit more
confident.

In case you rendered a dot file first, you can use the `render` command to make an image out of it:
```
$ bin/cake ModelGraph render /tmp/relations.dot /tmp/relations.svg
```

Enjoy!
