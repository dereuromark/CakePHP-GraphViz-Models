<?php

namespace ModelGraph\Test\TestCase\Command;

use Cake\Console\ConsoleIo;
use Cake\Core\Plugin;
use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;
use ModelGraph\Command\ModelGraphCommand;
use Shim\TestSuite\ConsoleOutput;
use Shim\TestSuite\TestTrait;

class ModelGraphCommandTest extends TestCase {

	use ConsoleIntegrationTestTrait;
	use TestTrait;

	/**
	 * @var \ModelGraph\Command\ModelGraphCommand
	 */
	protected $command;

	/**
	 * @var \Shim\TestSuite\ConsoleOutput
	 */
	protected $out;

	/**
	 * setUp method
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->out = new ConsoleOutput();
		$io = new ConsoleIo($this->out);

		$this->command = new ModelGraphCommand();

		$this->testFilePath = Plugin::path('ModelGraph') . 'tests' . DS . 'test_files' . DS;

		$this->useCommandRunner();
	}

	/**
	 * tearDown
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * @return void
	 */
	public function testGetModels(): void {
		$models = $this->invokeMethod($this->command, 'getModels');
		$this->assertSame(['TestRecords'], $models);
	}

	/**
	 * @return void
	 */
	public function testExecute() {
		$this->exec('model_graph');

		$this->assertExitSuccess();
	}

	/**
	 * @return void
	 */
	public function _testRender() {
		$in = $this->testFilePath . 'graph.dot';
		$out = TMP . 'graph.svg';
		$this->Shell->render($in, $out);

		$this->assertFileExists($out);
	}

	/**
	 * @return void
	 */
	public function _testGenerate() {
		$this->Shell->params['format'] = 'dot';
		$this->Shell->generate();

		$out = TMP . 'graph.dot';
		$this->assertFileExists($out);
	}

}
