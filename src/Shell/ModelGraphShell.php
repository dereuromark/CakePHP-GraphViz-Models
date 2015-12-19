<?php

namespace ModelGraph\Shell;

use Bake\Shell\Task\BakeTemplateTask;
use Cake\Console\Shell;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Log\Log;

use Cake\ORM\Association;
use Cake\ORM\TableRegistry;
use Migrations\Shell\Task\MigrationSnapshotTask;
use phpDocumentor\GraphViz\Edge;
use phpDocumentor\GraphViz\Graph;
use phpDocumentor\GraphViz\Node;
use ReflectionClass;

/**
 * CakePHP ModelGraph
 *
 * This shell examines all models in the current application and its plugins,
 * finds all relations between them, and then generates a graphical representation
 * of those.  The graph is built using an excellent GraphViz tool.
 *
 * <b>Usage:</b>
 *
 * <code>
 * $ bin/cake ModelGraph generate [filename] [format]
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
class ModelGraphShell extends Shell {

	/**
	 * Default image format.
	 */
	const DEFAULT_FORMAT_IMG = 'svg';

	/**
	 * Change this to something else if you have a plugin with the same name.
	 */
	const GRAPH_LEGEND = 'Graph Legend';

	/**
	 * Graph settings
	 *
	 * Consult the GraphViz documentation for node, edge, and
	 * graph attributes for more information.
	 *
	 * @link http://www.graphviz.org/doc/info/attrs.html
	 */
	public $graphSettings = array(
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
	);

	/**
	 * Relations settings
	 *
	 * Using Crow's Foot Notation for CakePHP model relations.
	 *
	 * NOTE: Order of the relations in this list is sometimes important.
	 */
	public $relationsSettings = array(
		Association::ONE_TO_ONE => array('label' => 'hasOne', 'dir' => 'both', 'color' => 'magenta', 'arrowhead' => 'tee', 'arrowtail' => 'none', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
		Association::ONE_TO_MANY => array('label' => 'hasMany', 'dir' => 'both', 'color' => 'blue', 'arrowhead' => 'crow', 'arrowtail' => 'none', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
		Association::MANY_TO_ONE => array('label' => 'belongsTo', 'dir' => 'both', 'color' => 'blue', 'arrowhead' => 'none', 'arrowtail' => 'crow', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
		Association::MANY_TO_MANY => array('label' => 'belongsToMany', 'dir' => 'both', 'color' => 'red', 'arrowhead' => 'crow', 'arrowtail' => 'crow', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
	);

	/**
	 * Miscellaneous settings
	 *
	 * These are settings that change the behavior
	 * of the application, but which I didn't feel
	 * safe enough to send to GraphViz.
	 */
	public $miscSettings = array(
		// If true, graphs will use only real model names (via className).  If false,
		// graphs will use whatever you specified as the name of relationship class.
		// This might get very confusing, so you mostly would want to keep this as true.
		'realModels' => true, //TODO: make this alias => true/false better?

		// If set to not empty value, the value will be used as a date() format, that
		// will be appended to the main graph label. Set to empty string or null to avoid
		// timestamping generated graphs.
		'timestamp' => ' [Y-m-d H:i:s]',
	);

	/**
	 * @var \phpDocumentor\GraphViz\Graph
	 */
	public $graph;

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
			$this->error('Input file cannot be found.');
			return;
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
			$this->error(implode(PHP_EOL, $output));
			return;
		}
		$this->out('Done :) Image can be found in ' . $fileName, 1, Shell::VERBOSE);
	}

	/**
	 * Generates the image from the DB schema.
	 *
	 * @param string|null $outputFile
	 * @return int|null|void
	 */
	public function generate($outputFile = null) {
		$this->graphSettings = (array)Configure::read('GraphViz') + $this->graphSettings;

		// Prepare graph settings
		$graphSettings = $this->graphSettings;
		if (!empty($this->miscSettings['timestamp'])) {
			$graphSettings['label'] .= date($this->miscSettings['timestamp']);
		}

		// Initialize the graph
		$this->graph = new Graph('models');
		foreach ($graphSettings as $key => $value) {
			call_user_func(array($this->graph, 'set' . $key), $value);
		}

		$models = $this->_getModels();
		$relationsData = $this->_getRelations($models, $this->relationsSettings);
		$this->_buildGraph($models, $relationsData, $this->relationsSettings);

		$type = $this->_detectType($outputFile, ['dot']);

		if ($outputFile) {
			$fileName = $outputFile;
		} else {
			$fileName = TMP . 'graph.' . $type;
		}

		$this->_outputGraph($fileName, $type);
		$this->out('Done :) Result can be found in ' . $fileName, 1, Shell::VERBOSE);
	}

	/**
	 * Get a list of all models to process
	 *
	 * This will only include models that can be instantiated, and plugins that are loaded by the bootstrap
	 *
	 * @return array
	 */
	protected function _getModels() {
		$result = array();

		if (Plugin::loaded('Migrations')) {
			$task = new MigrationSnapshotTask();
			$task->params['require-table'] = false;
			$task->BakeTemplate = new BakeTemplateTask();
			$task->BakeTemplate->viewVars['name'] = null;
			$task->connection = 'default';
			$data = $task->templateData();
			$tables = $data['tables'];
			return ['app' => $tables];
		}

		//can be removed?
		$appModels = App::objects('Model', null, false);
		$result['app'] = array();
		foreach ($appModels as $model) {
			if (strpos($model, 'AppModel') !== false) {
				continue;
			}
			$result['app'][] = $model;
		}
		$plugins = Plugin::loaded();
		foreach ($plugins as $plugin) {
			if (in_array($plugin, array('DebugKit', 'Migrations'))) {
				continue;
			}

			$pluginModels = App::objects($plugin . '.Model', null, false);
			if (!empty($pluginModels)) {
				if (!isset($result[$plugin])) {
					$result[$plugin] = array();
				}

				foreach ($pluginModels as $model) {
					if (strpos($model, 'AppModel') !== false) {
						continue;
					}
					$result[$plugin][] = $plugin . '.' . $model;
				}
			}
		}

		return $result;
	}

	/**
	 * Get the list of relations for given models
	 *
	 * @param array $modelsList List of models by module (apps, plugins, etc)
	 * @param array $relationsSettings Relationship settings
	 * @return array
	 */
	protected function _getRelations($modelsList, $relationsSettings) {
		$result = array();

		foreach ($modelsList as $plugin => $models) {
			foreach ($models as $model) {

				$modelInstance = TableRegistry::get($model);
				$this->out('Checking: ' . $model . ' (table ' . $modelInstance->table() . ')', 1, Shell::VERBOSE);

				$associations = $modelInstance->associations();
				foreach ($associations as $association) {
					$relationType = $association->type();
					$relationModel = $association->table();
					$this->out(' - Relation detected: ' . $model . ' '. $this->relationsSettings[$relationType]['label'] . ' ' . $relationModel, 1, Shell::VERBOSE);

					$result[$plugin][$model][$relationType][] = $relationModel;
				}

				continue;

				foreach ($relationsSettings as $relationType => $settings) {
					if (empty($modelInstance->$relationType) || !is_array($modelInstance->$relationType)) {
						continue;
					}

					$relations = $modelInstance->$relationType;

					if ($this->miscSettings['realModels']) {
						$result[$plugin][$model][$relationType] = array();
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
	 * @param string $label
	 * @param array $attributes
	 * @return \phpDocumentor\GraphViz\Graph
	 */
	protected function _addCluster($graph, $name, $label = null, $attributes = array()) {
		if ($label === null) {
			$label = $name;
		}
		if (!$graph->hasGraph('cluster_' . $name)) {
			$clusterGraph = Graph::create('cluster_' . $name);
			$this->_addAttributes($clusterGraph, $attributes);
			$this->graph->addGraph($clusterGraph->setLabel($label));
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
			call_user_func(array($object, 'set' . $key), $value);
		}
		return $object;
	}

	/**
	 * Populate graph with nodes and edges
	 *
	 * @param array $models Available models
	 * @param array $relations Availalbe relationships
	 * @param array $settings Settings
	 * @return void
	 */
	protected function _buildGraph($modelsList, $relationsList, $settings) {
		// We'll collect apps and plugins in here
		$plugins = array();

		// Add special cluster for Legend
		$plugins[] = self::GRAPH_LEGEND;
		$this->_buildGraphLegend($settings);

		// Add nodes for all models
		foreach ($modelsList as $plugin => $models) {
			if (!in_array($plugin, $plugins)) {
				$plugins[] = $plugin;
				$pluginGraph = $this->_addCluster($this->graph, $plugin);
			}

			foreach ($models as $model) {
				$label = preg_replace("/^$plugin\./", '', $model);
				$node = Node::create($model, $label)->setShape('box')->setFontname('Helvetica')->setFontsize(10);
				$pluginGraph = $this->_addCluster($this->graph, $plugin);
				$pluginGraph->setNode($node);
			}
		}

		// Add all relations
		foreach ($relationsList as $plugin => $models) {
			if (!in_array($plugin, $plugins)) {
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
	protected function _buildGraphLegend($relationsSettings) {
		$legendNodeSettings = array(
				'shape' => 'box',
				'width' => 0.5,
				'fontname' => 'Helvetica',
				'fontsize' => 10,
			);

		$legend = $this->_addCluster($this->graph, self::GRAPH_LEGEND);

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
	 * @param string $fileName File to save graph to (full path)
	 * @param string $format Any of the GraphViz supported formats, or dot
	 * @return bool Success
	 */
	protected function _outputGraph($fileName = null, $format = null) {
		if ($format === 'dot') {
			file_put_contents($fileName, (string)$this->graph);
			return true;
		}

		// Fall back on PNG if no format was given
		if (empty($format)) {
			$format = self::DEFAULT_FORMAT_IMG;
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
	public function getOptionParser()
	{
		$parser = parent::getOptionParser();

		$generateParser = [
			'options' => [
				'format' => [
					'short' => 'f',
					'help' => 'Format to render. Supports all GraphViz ones and dot for plain dot output. Defaults to svg.',
					'default' => null
				]
			]
		];
		$renderParser = $generateParser;
		$renderParser['arguments'][] = [
			'name' => 'input.dot',
			'required' => true
		];
		$renderParser['arguments'][] = [
			'name' => 'output.ext',
		];

		$parser->description(
			'Render graphs from the model relations.'
		)->addSubcommand('generate', [
				'help' => 'Generate the graph.',
				'parser' => $generateParser
		])->addSubcommand('render', [
				'help' => 'Transform a dot file into an image.',
				'parser' => $renderParser
			]);

		return $parser;
	}

	/**
	 * @param string|null $outputFile
	 * @param array $exclude
	 * @return string
     */
	protected function _detectType($outputFile, $exclude = []) {
		$type = self::DEFAULT_FORMAT_IMG;
		if (!empty($this->params['format']) && !in_array($this->params['format'], $exclude)) {
			$type = $this->params['format'];
		} elseif ($outputFile) {
			$detectedType = pathinfo($outputFile, PATHINFO_EXTENSION);
			if ($detectedType && !in_array($detectedType, $exclude)) {
				$type = $detectedType;
			}
		}
		return $type;
	}

}
