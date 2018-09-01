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
		return [self::$Source, function($filepath){
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
	public final static function register(File $File): void {
		array_push(self::$Raw, $File->toReadingBuffer()->process(function ($value){
			return (new Regexp('/\s*\\?>$/'))->erase(trim($value)) . "\n?>\n"; }));
	}

	/**
	 * @param Path $Path
	 * @return \Generator
	 * @throws \Exception
	 */
	public static final function compile(Path $Path): \Generator {
		self::$History = [];

		foreach (self::$Raw as $Reader){
			yield from $Reader->read();
		}

		yield from parent::compile($Path);
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
Delegate::register((new Path(dirname(__DIR__), 'includes', 'prepared.php'))->toFile());
