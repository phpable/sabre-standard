<?php
namespace Able\Sabre\Standard;

use \Able\Facades\AFacade;

use \Able\IO\File;
use \Able\IO\Path;
use \Able\IO\Abstractions\IReader;

use \Able\Reglib\Reglib;
use \Able\Reglib\Regexp;

use \Able\Sabre\Compiler;
use \Able\Sabre\Structures\STrap;
use \Able\Sabre\Structures\SToken;

use \Able\Helpers\Str;
use \Able\Helpers\Arr;

/**
 * @method static void hook(string $token, callable $Handler)
 * @method static void trap(STrap $Signature)
 * @method static void token(SToken $Signature)
 * @method static void extend(string $token, SToken $Signature)
 * @method static void finalize(string $token, SToken $Signature)
 */
class Delegate extends AFacade {

	/**
	 * @var string
	 */
	protected static $Recipient = Compiler::class;

	/**
	 * @var Path
	 */
	private static $Source = null;

	/**
	 * @param Path $Source
	 */
	public final static function registerSourceDirectory(Path $Source){
		if (!$Source->isExists() || !$Source->isDirectory()){
			throw new Exception('Source path does not exist or not a directory!');
		}

		self::$Source = $Source;
	}

	/**
	 * @return Path
	 * @throws \Exception
	 */
	protected final static function getSoursePath(): Path {
		return self::$Source->toPath();
	}

	/**
	 * @var string[]
	 */
	private static $History = [];

	/**
	 * @return string[]
	 */
	public final static function history(){
		return self::$History;
	}

	/**
	 * @return array
	 */
	protected static final function provide(): array {
		return [function($filepath){
			array_push(self::$History, $filepath);
		}];
	}

	/**
	 * @var Files[]
	 */
	private static $Raw = [];

	/**
	 * @param File $File
	 * @return void
	 */
	public final static function prepend(File $File): void {
		array_push(self::$Raw, $File->toReadingBuffer()->process(function ($value){
			return (new Regexp('/\s*\\?>$/'))->erase(trim($value)) . "\n?>\n"; }));
	}

	/**
	 * @const int
	 */
	public const CO_SKIP_RAW = 0b0001;

	/**
	 * @const int
	 */
	public const CO_SKIP_CALL = 0b0010;

	/**
	 * @param IReader $Reader
	 * @param int $mode
	 * @param string $name
	 * @return \Generator
	 * @throws \Exception
	 */
	public static final function compile(IReader $Reader,  string $name = null, int $mode = 0b0000): \Generator {
		self::$History = [];

		$name = $name ?? 'main_' . md5(Str::join('|', microtime(true),
			__CLASS__, __METHOD__, $Reader->getLocation()));

		yield '<?php if (!function_exists("' . $name
			. '")){ function ' . $name . '($__obj, $__data){ extract($__data); unset($__data); ?>';

		yield from parent::compile($Reader);

		yield '<?php }}?>';
		if (~$mode & self::CO_SKIP_CALL) {
			yield '<?php ' . $name . '(__init(), $__data ?? []);?>';
		}

		if (~$mode & self::CO_SKIP_RAW) {
			foreach (self::$Raw as $Reader) {
				yield from $Reader->read();
			}
		}
	}

	/**
	 * Initialize the standard compiler's behavior.
	 * @throws \Exception
	 */
	public final static function initialize(): void {
		try {
			if (!file_exists($Path = (new Path(__DIR__))->getParent()->append('includes', 'behavior.php'))) {
				throw new \Exception('Can not load behavior!');
			}

			include($Path->toString());
		}catch (\Throwable $Exception){

			/** @noinspection PhpUnhandledExceptionInspection */
			throw new \ErrorException('Cannot load behavior: ' . $Exception->getMessage(), 0, 1,
				$Exception->getFile(), $Exception->getLine(), $Exception);
		}
	}
}

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::prepend((new Path(dirname(__DIR__), 'includes', 'prepared.php'))->toFile());
