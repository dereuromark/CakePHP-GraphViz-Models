# CakePHP ModelGraph plugin

This is a CakePHP shell that will find all Tables in your CakePHP application and
plugins, figure out the relationships between them, and will build a nice graph,
visualizing those relationships for you.

It supports CakePHP 3.x, and requires PHP 5.4+ or greater. Windows is also supporting.

Intallation via Composer

```
require: {
	"mamchenkov/cakephp-graphviz-models": "dev-master"
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

## Usage

The simplest way to use this shell is just to run it via CakePHP console:

```
$ Console/cake ModelGraph generate
```

This should generate a graph.png image in your TMP directory.  Please have a look.

If you need more control, there are two options that this shell understand from the
command line: filename and format.   You can use either the filename option like so:

```
$ Console/cake ModelGraph generate /tmp/my_models.png
```

Or you can use both options together like so:

```
$ Console/cake ModelGraph generate /tmp/my_models.svg svg
```

No special magic is done about the filename.  What You Give Is What You Get.  As for the
format, you can use anything that GraphViz supports and understands.

If you are still looking for more control, have a look inside the script.  There are
plenty of settings, options, parameters, and comments for you to make sense of it all. It
might be helpful to get familiar with GraphViz Dot Language, just to feel a tiny bit more
confident.

Enjoy!
