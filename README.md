# CakePHP ModelGraph plugin

[![CI](https://github.com/dereuromark/cakephp-model-graph/workflows/CI/badge.svg?branch=master)](https://github.com/dereuromark/cakephp-model-graph/actions?query=workflow%3ACI+branch%3Amaster)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%207.2-8892BF.svg)](https://php.net/)
[![License](https://poser.pugx.org/dereuromark/cakephp-model-graph/license)](https://packagist.org/packages/dereuromark/cakephp-model-graph)
[![Coding Standards](https://img.shields.io/badge/cs-PSR--2--R-yellow.svg)](https://github.com/php-fig-rectified/fig-rectified-standards)

This is a CakePHP plugin that will find all Tables in your CakePHP application and
plugins, figure out the relationships between them, and will build a nice graph,
visualizing those relationships for you.

Note: This branch is for **CakePHP 4.0+**. See [version map](https://github.com/dereuromark/cakephp-model-graph/wiki#cakephp-version-map) for details.

## Requirements

This script relies on `phpDocumentor/Graphviz` package, rather than directly on the command-line dot tool.
For a graphical result instead of just text-info you will need to install the Graphviz command line tool incl. `dot`.

If on Windows, make sure you set the path in Configure key `GraphViz.path`:
```php
// config/app.php
'GraphViz' => [
	'path' => 'C:\...\graphviz\bin\\',
],
```

## Installation

You can install this plugin into your CakePHP application using [composer](https://getcomposer.org):
```
composer require --dev dereuromark/cakephp-model-graph:dev-master
```

Note: This is not meant for production, so make sure you use the `--dev` flag and install it as development-only tool.


## Setup

Don't forget to load it under your bootstrap function in `Application.php`:
```php
$this->addPlugin('ModelGraph');
```


## Usage

The simplest way to use this shell is just to run it via CakePHP console:

```
$ bin/cake model_graph
```

This should generate a graph.png image in your TMP directory.  Please have a look.

If you need more control, there are two options that this shell understand from the
command line: filename and format.   You can use either the filename option like so:

```
$ bin/cake model_graph /tmp/relations.dot
```
It will derive the format from the extension if possible.

You can provide the format manually, as well:

```
$ bin/cake model_graph -f svg
```

No special magic is done about the filename.  What You Give Is What You Get.  As for the
format, you can use anything that GraphViz supports and understands.

If you are still looking for more control, have a look inside the script.  There are
plenty of settings, options, parameters, and comments for you to make sense of it all. It
might be helpful to get familiar with GraphViz Dot Language, just to feel a tiny bit more
confident.

In case you rendered a dot file first, you can use the `render` command to make an image out of it:
```
$ bin/cake model_graph render /tmp/relations.dot /tmp/relations.svg
```

Enjoy!
