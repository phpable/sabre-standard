<?php
namespace Able\Sabre\Standard;

use \Able\Facades\AFacade;

use \Able\IO\File;
use \Able\IO\Path;

use \Able\Reglib\Reglib;
use \Able\Reglib\Regexp;

/**
 * @method static void prepend(File $File)
 * @method static \Generator compile(File $File)
 * @method static void trap(STrap $Signature)
 * @method static void token(SToken $Signature)
 * @method static void extend(string $token, SToken $Signature)
 */
class Compiler extends AFacade {

	/**
	 * @var string
	 */
	protected static $Recipient = \Able\Sabre\Compiler::class;

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
	 * @return array
	 */
	protected static final function provide(): array {
		return [self::$Source];
	}
}

try {
	if (!file_exists($Path = (new Path(__DIR__))->getParent()->append('sources', 'behavior.php'))) {
		throw new \Exception('Can not load behavior!');
	}

	include($Path->toString());
}catch (\Throwable $Exception){

	/** @noinspection PhpUnhandledExceptionInspection */
	throw new \Exception('Cannot load behavior: ' . $Exception->getMessage());
}
