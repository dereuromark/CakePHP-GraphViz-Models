<?php
namespace ModelGraph\Test\TestCase\Shell;

use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOutput;
use Cake\Core\Plugin;
use Cake\TestSuite\TestCase;

/**
 * Class TestCompletionStringOutput
 *
 */
class TestClearOutput extends ConsoleOutput {

	public $output = '';

	protected function _write($message) {
		$this->output .= $message;
	}

}

/**
 */
class ModelGraphShellTest extends TestCase {

	/**
	 * @var \ModelGraph\Shell\ModelGraphShell
	 */
	protected $Shell;

	/**
	 * setUp method
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$this->out = new TestClearOutput();
		$io = new ConsoleIo($this->out);

		$this->Shell = $this->getMock(
			'ModelGraph\Shell\ModelGraphShell',
			['in', 'err', '_stop'],
			[$io]
		);

		$this->testFilePath = Plugin::path('ModelGraph') . 'tests' . DS . 'test_files' . DS;
	}

	/**
	 * tearDown
	 *
	 * @return void
	 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Shell);
	}

	/**
	 * @return void
	 */
	public function testRender() {
		$in = $this->testFilePath . 'graph.dot';
		$out = TMP . 'graph.svg';
		$this->Shell->render($in, $out);

		$this->assertFileExists($out);
	}

	/**
	 * @return void
	 */
	public function testGenerate() {
		$this->Shell->params['format'] = 'dot';
		$this->Shell->generate();

		$out = TMP . 'graph.dot';
		$this->assertFileExists($out);
	}

}
