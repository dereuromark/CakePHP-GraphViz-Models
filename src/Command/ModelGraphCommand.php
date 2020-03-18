<?php

namespace ModelGraph\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Filesystem\Folder;
use Cake\Log\Log;
use Cake\ORM\Association;
use Cake\ORM\TableRegistry;
use phpDocumentor\GraphViz\Edge;
use phpDocumentor\GraphViz\Graph;
use phpDocumentor\GraphViz\Node;

/**
 * CakePHP ModelGraph
 *
 * This command examines all models in the current application and its plugins,
 * finds all relations between them, and then generates a graphical representation
 * of those. The graph is built using an excellent GraphViz tool.
 *
 * <b>Usage:</b>
 *
 * <code>
 * $ bin/cake model_graph generate [filename] [format]
 * </code>
 *
 * <b>Parameters:</b>
 *
 * * filename - an optional full path to the output file. If omitted, graph.png in
 *              TMP folder will be used
 * * format - an optional output format, supported by GraphViz (png, svg, etc)
 *
 * @author Leonid Mamchenkov <leonid@mamchenkov.net>
 * @author Mark Scherer
 */
class ModelGraphCommand extends Command {

	/**
	 * Default image format.
	 */
	protected const DEFAULT_FORMAT_IMG = 'svg';

	/**
	 * Change this to something else if you have a plugin with the same name.
	 */
	protected const GRAPH_LEGEND = 'Graph Legend';

	/**
	 * @var \Cake\Console\ConsoleIo
	 */
	protected $io;

	/**
	 * @var string[]
	 */
	protected $blacklistedPlugins = [
		'DebugKit',
		'Migrations',
	];

	/**
	 * Consult the GraphViz documentation for node, edge, and
	 * graph attributes for more information.
	 *
	 * @var array
	 * @link http://www.graphviz.org/doc/info/attrs.html
	 */
	protected $graphSettings = [
		'path' => '', // Where the bin dir of dot is located at - if not added to PATH env
		'label' => 'CakePHP Model Relations',
		'labelloc' => 't',
		'fontname' => 'Helvetica',
		'fontsize' => 12,
		//
		// Tweaking these might produce better results
		//
		'concentrate' => 'true', // Join multiple connecting lines between same nodes
		'landscape' => 'false', // Rotate resulting graph by 90 degrees
		'rankdir' => 'TB', // Interpret nodes from Top-to-Bottom or Left-to-Right (use: LR)
	];

	/**
	 * Using Crow's Foot Notation for CakePHP model relations.
	 *
	 * NOTE: Order of the relations in this list is sometimes important.
	 *
	 * @var array
	 */
	protected $relationsSettings = [
		Association::ONE_TO_ONE => ['label' => 'hasOne', 'dir' => 'both', 'color' => 'magenta', 'arrowhead' => 'tee', 'arrowtail' => 'none', 'fontname' => 'Helvetica', 'fontsize' => 10],
		Association::ONE_TO_MANY => ['label' => 'hasMany', 'dir' => 'both', 'color' => 'blue', 'arrowhead' => 'crow', 'arrowtail' => 'none', 'fontname' => 'Helvetica', 'fontsize' => 10],
		Association::MANY_TO_ONE => ['label' => 'belongsTo', 'dir' => 'both', 'color' => 'blue', 'arrowhead' => 'none', 'arrowtail' => 'crow', 'fontname' => 'Helvetica', 'fontsize' => 10],
		Association::MANY_TO_MANY => ['label' => 'belongsToMany', 'dir' => 'both', 'color' => 'red', 'arrowhead' => 'crow', 'arrowtail' => 'crow', 'fontname' => 'Helvetica', 'fontsize' => 10],
	];

	/**
	 * These are settings that change the behavior
	 * of the application, but which I didn't feel
	 * safe enough to send to GraphViz.
	 *
	 * @var array
	 */
	protected $miscSettings = [
		// If true, graphs will use only real model names (via className).  If false,
		// graphs will use whatever you specified as the name of relationship class.
		// This might get very confusing, so you mostly would want to keep this as true.
		'realModels' => true, //TODO: make this alias => true/false better?

		// If set to not empty value, the value will be used as a date() format, that
		// will be appended to the main graph label. Set to empty string or null to avoid
		// timestamping generated graphs.
		'timestamp' => ' [Y-m-d H:i:s]',
	];

	/**
	 * @var \phpDocumentor\GraphViz\Graph
	 */
	protected $graph;

	/**
	 * Displays in textual form and then render dot and image files.
	 *
	 * @param \Cake\Console\Arguments $args The command arguments.
	 * @param \Cake\Console\ConsoleIo $io The console io
	 * @return int|null|void The exit code or null for success
	 */
	public function execute(Arguments $args, ConsoleIo $io) {
		$this->io = $io;

		$models = $this->getModels();
		$relationsData = $this->_getRelations($models, $this->relationsSettings);
		//output text

		$fileName = $this->generate($models, $relationsData);
		$io->out('Done :) Result can be found in ' . $fileName, 1, ConsoleIo::VERBOSE);

		$imageFileName = $this->render($fileName);
		$io->out('Done :) Image can be found as ' . $imageFileName, 1, ConsoleIo::VERBOSE);

		$io->success('All done.');
	}

	/**
	 * Transforms an existing dot file into an image
	 *
	 * @param string $inputFile
	 * @param string|null $outputFile
	 * @return int|null|void
	 */
	public function render($inputFile, $outputFile = null) {
		$this->graphSettings = (array)Configure::read('GraphViz') + $this->graphSettings;

		if (!file_exists($inputFile)) {
			throw new \RuntimeException('Input file cannot be found.');
		}

		$type = $this->_detectType($outputFile, ['dot']);

		if ($outputFile) {
			$fileName = $outputFile;
		} else {
			$fileName = TMP . 'graph.' . $type;
		}

		$fileArg = escapeshellarg($inputFile);
		exec($this->graphSettings['path'] . "dot -T$type -o$fileName < $fileArg 2>&1", $output, $code);
		if ($code !== 0) {
			throw new \RuntimeException(implode(PHP_EOL, $output));
		}

		return $fileName;
	}

	/**
	 * Generates the image from the DB schema.
	 *
	 * @param string|null $outputFile
	 * @return int|null|void
	 */
	public function generate(array $models, array $relationsData, $outputFile = null) {
		$this->graphSettings = (array)Configure::read('GraphViz') + $this->graphSettings;

		// Prepare graph settings
		$graphSettings = $this->graphSettings;
		if (!empty($this->miscSettings['timestamp'])) {
			$graphSettings['label'] .= date($this->miscSettings['timestamp']);
		}

		// Initialize the graph
		$this->graph = Graph::create('models');
		foreach ($graphSettings as $key => $value) {
			call_user_func([$this->graph, 'set' . $key], $value);
		}

		$this->_buildGraph($models, $relationsData, $this->relationsSettings);

		$type = 'dot'; //$this->_detectType($outputFile);

		if ($outputFile) {
			$fileName = $outputFile;
		} else {
			$fileName = TMP . 'graph.' . $type;
		}

		$this->_outputGraph($fileName, $type);

		return $fileName;
	}

	/**
	 * Get a list of all models to process
	 *
	 * This will only include models that can be instantiated, and plugins that are loaded by the bootstrap
	 *
	 * @return array
	 */
	protected function getModels(): array {
		$result = $this->_getModels();

		$plugins = Plugin::loaded();
		foreach ($plugins as $plugin) {
			if (in_array($plugin, $this->blacklistedPlugins, true)) {
				continue;
			}

			$pluginModels = $this->_getModels($plugin);

			foreach ($pluginModels as $model) {
				if (strpos($model, 'AppModel') !== false) {
					continue;
				}
				$result[] = $plugin . '.' . $model;
			}
		}

		return $result;
	}

	/**
	 * @param \ModelGraph\Command\string|null $plugin
	 *
	 * @return string[]
	 */
	protected function _getModels(?string $plugin = null): array
	{
		$paths = App::classPath('Model/Table', $plugin);
		$files = $this->getFiles($paths);

		$models = [];
		foreach ($files as $file) {
			if (!preg_match('/^(\w+)Table$/', $file, $matches)) {
				continue;
			}
			$models[] = $matches[1];
		}

		return $models;
	}

	/**
	 * @param array $folders
	 *
	 * @return array
	 */
	protected function getFiles(array $folders): array {
		$names = [];
		foreach ($folders as $folder) {
			$folderContent = (new Folder($folder))->read(Folder::SORT_NAME, true);

			foreach ($folderContent[1] as $file) {
				$name = pathinfo($file, PATHINFO_FILENAME);
				$names[] = $name;
			}

			foreach ($folderContent[0] as $subFolder) {
				$folderContent = (new Folder($folder . $subFolder))->read(Folder::SORT_NAME, true);

				foreach ($folderContent[1] as $file) {
					$name = pathinfo($file, PATHINFO_FILENAME);
					$names[] = $subFolder . '.' . $name;
				}
			}
		}

		return $names;
	}

	/**
	 * Get the list of relations for given models
	 *
	 * @param array $modelsList List of models by module (apps, plugins, etc)
	 * @param array $relationsSettings Relationship settings
	 * @return array
	 */
	protected function _getRelations(array $modelsList, array $relationsSettings): array {
		$result = [];

		foreach ($modelsList as $model) {
			[$plugin, $model] = pluginSplit($model);

			$modelInstance = TableRegistry::get($model);
			//$io->out('Checking: ' . $model . ' (table ' . $modelInstance->table() . ')', 1, ConsoleIo::VERBOSE);

			/** @var \Cake\ORM\Association[] $associations */
			$associations = $modelInstance->associations();
			foreach ($associations as $association) {
				$relationType = $association->type();
				$relationModel = $association->getAlias();
				//$io->out(' - Relation detected: ' . $model . ' ' . $this->relationsSettings[$relationType]['label'] . ' ' . $relationModel, 1, ConsoleIo::VERBOSE);

				$result[$plugin][$model][$relationType][] = $relationModel;
			}

			//TODO
			continue;

			foreach ($relationsSettings as $relationType => $settings) {
				if (empty($modelInstance->$relationType) || !is_array($modelInstance->$relationType)) {
					continue;
				}

				$relations = $modelInstance->$relationType;

				if ($this->miscSettings['realModels']) {
					$result[$plugin][$model][$relationType] = [];
					foreach ($relations as $name => $value) {
						if (is_array($value) && !empty($value) && !empty($value['className'])) {
							$result[$plugin][$model][$relationType][] = $value['className'];
						} else {
							$result[$plugin][$model][$relationType][] = $name;
						}
					}
				} else {
					$result[$plugin][$model][$relationType] = array_keys($relations);
				}
			}
		}

		return $result;
	}

	/**
	 * Add a cluster to a graph
	 *
	 * If the cluster already exists on the graph, then the cluster graph is returned
	 *
	 * @param \phpDocumentor\GraphViz\Graph $graph
	 * @param string $name
	 * @param string|null $label
	 * @param array $attributes
	 * @return \phpDocumentor\GraphViz\Graph
	 */
	protected function _addCluster($graph, $name, $label = null, $attributes = []) {
		if ($label === null) {
			$label = $name;
		}
		if (!$graph->hasGraph('cluster_' . $name)) {
			$clusterGraph = Graph::create('cluster_' . $name);
			$this->_addAttributes($clusterGraph, $attributes);
			//$this->graph->addGraph($clusterGraph->setName($label));
		} else {
			$clusterGraph = $this->graph->getGraph('cluster_' . $name);
		}
		return $clusterGraph;
	}

	/**
	 * Set attributes on an object
	 *
	 * @param mixed $object
	 * @param array $attributes
	 * @return mixed $object
	 */
	protected function _addAttributes($object, $attributes) {
		foreach ($attributes as $key => $value) {
			call_user_func([$object, 'set' . $key], $value);
		}
		return $object;
	}

	/**
	 * Populate graph with nodes and edges
	 *
	 * @param array $modelsList Available models
	 * @param array $relationsList Availalbe relationships
	 * @param array $settings Settings
	 * @return void
	 */
	protected function _buildGraph($modelsList, $relationsList, $settings): void {
		// We'll collect apps and plugins in here
		$plugins = [];

		// Add special cluster for Legend
		$plugins[] = static::GRAPH_LEGEND;
		$this->_buildGraphLegend($settings);

		// Add nodes for all models
		foreach ($modelsList as $model) {
			[$plugin, $model] = pluginSplit($model);

			if (!in_array($plugin, $plugins, true)) {
				$plugins[] = $plugin;
				$pluginGraph = $this->_addCluster($this->graph, $plugin);
			}

			$label = preg_replace("/^$plugin\\./", '', $model);
			$node = Node::create($model, $label)->setShape('box')->setFontname('Helvetica')->setFontsize(10);
			$pluginGraph = $this->_addCluster($this->graph, $plugin);
			$pluginGraph->setNode($node);
		}

		// Add all relations
		foreach ($relationsList as $plugin => $models) {
			if (!in_array($plugin, $plugins, true)) {
				$plugins[] = $plugin;
				$pluginGraph = $this->_addCluster($this->graph, $plugin);
			}

			foreach ($models as $model => $relations) {
				foreach ($relations as $relationType => $relatedModels) {

					$relationsSettings = $settings[$relationType];
					$relationsSettings['label'] = ''; // no need to pollute the graph with too many labels

					foreach ($relatedModels as $relatedModel) {
						$modelNode = $this->graph->findNode($model);
						if ($modelNode === null) {
							Log::error('Could not find node for ' . $model);
						} else {
							$relatedModelNode = $this->graph->findNode($relatedModel);
							if ($relatedModelNode === null) {
								Log::error('Could not find node for ' . $relatedModel);
							} else {
								$edge = Edge::create($modelNode, $relatedModelNode);
								$this->graph->link($edge);
								$this->_addAttributes($edge, $relationsSettings);
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Add graph legend
	 *
	 * For every type of the relationship in CakePHP we add two nodes (from, to)
	 * to the graph and then link them, using the settings of each relationship
	 * type.  Nodes are grouped into the Graph Legend cluster, so they don't
	 * interfere with the rest of the nodes.
	 *
	 * @param array $relationsSettings Array with relation types and settings
	 * @return void
	 */
	protected function _buildGraphLegend(array $relationsSettings): void {
		$legendNodeSettings = [
				'shape' => 'box',
				'width' => 0.5,
				'fontname' => 'Helvetica',
				'fontsize' => 10,
			];

		$legend = $this->_addCluster($this->graph, static::GRAPH_LEGEND);

		foreach ($relationsSettings as $relation => $relationSettings) {
			$from = $relation . '_from';
			$to = $relation . '_to';

			$fromNode = Node::create($from, 'A');
			$this->_addAttributes($fromNode, $legendNodeSettings);
			$legend->setNode($fromNode);

			$toNode = Node::create($to, 'B');
			$this->_addAttributes($toNode, $legendNodeSettings);
			$legend->setNode($toNode);

			$edge = Edge::create($fromNode, $toNode);
			$this->_addAttributes($edge, $relationSettings);
			$legend->link($edge);
		}
	}

	/**
	 * Save graph to a file
	 *
	 * @param string|null $fileName File to save graph to (full path)
	 * @param string|null $format Any of the GraphViz supported formats, or dot
	 * @return bool Success
	 */
	protected function _outputGraph($fileName = null, $format = null) {
		if ($format === 'dot') {
			file_put_contents($fileName, (string)$this->graph);
			return true;
		}

		// Fall back on PNG if no format was given
		if (empty($format)) {
			$format = static::DEFAULT_FORMAT_IMG;
		}

		// Fall back on something when nothing is given
		if (empty($fileName)) {
			$fileName = 'graph.' . $format;
		}

		$this->graph->export($format, $fileName);

		return true;
	}

	/**
	 * Gets the option parser instance and configures it.
	 *
	 * @return \Cake\Console\ConsoleOptionParser
	 */
	public function getOptionParser(): ConsoleOptionParser {
		$parser = parent::getOptionParser();

		$generateParser = [
			'options' => [
				'format' => [
					'short' => 'f',
					'help' => 'Format to render. Supports all GraphViz ones and dot for plain dot output. Defaults to svg.',
					'default' => null,
				],
			],
		];
		$renderParser = $generateParser;
		$renderParser['arguments'][] = [
			'name' => 'input.dot',
		];
		$renderParser['arguments'][] = [
			'name' => 'output.ext',
		];

		$parser->setDescription(
			'Renders graph from the model relations.'
		)->addSubcommand('generate', [
				'help' => 'Generate the graph.',
				'parser' => $generateParser,
		])->addSubcommand('render', [
				'help' => 'Transform a dot file into an image.',
				'parser' => $renderParser,
			]);

		return $parser;
	}

	/**
	 * @param string|null $outputFile
	 * @param array $exclude
	 * @return string
	 */
	protected function _detectType($outputFile, $exclude = []) {
		$type = static::DEFAULT_FORMAT_IMG;
		if (!empty($this->params['format']) && !in_array($this->params['format'], $exclude, true)) {
			$type = $this->params['format'];
		} elseif ($outputFile) {
			$detectedType = pathinfo($outputFile, PATHINFO_EXTENSION);
			if ($detectedType && !in_array($detectedType, $exclude, true)) {
				$type = $detectedType;
			}
		}
		return $type;
	}

}
