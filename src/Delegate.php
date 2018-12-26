<?php
namespace Able\Sabre\Standard;

use \Able\Facades\AFacade;
use \Able\Facades\Structures\SInit;

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

use \Able\Minify\Php;

/**
 * @method static Compiler getRecipientInstance()
 * @method static void switch(string $token, callable $Handler)
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
	 * @const string
	 */
	private const DEFAULT_NAMESPACE = '*';

	/**
	 * @var Path[]
	 */
	private static $Sources = [];

	/**
	 * @param Path $Source
	 * @param string $namespace
	 * @throws \Exception
	 */
	public final static function registerSourcePath(Path $Source,
		string $namespace = self::DEFAULT_NAMESPACE): void {

		if (!$Source->isExists() || !$Source->isDirectory()){
			throw new Exception('Source path does not exist or not a directory!');
		}

		if (!preg_match('/^(?:[A-Za-z0-9]{3,32}|'
			. preg_quote(self::DEFAULT_NAMESPACE, '/') . ')$/', $namespace)){
				throw new \Exception(sprintf('Invalid namespace: %s!', $namespace));
		}

		if (isset(self::$Sources[$namespace = strtolower($namespace)])){
			throw new \Exception(sprintf('Namespace is already registered: %s!', $namespace));
		}

		self::$Sources[$namespace] = $Source;
	}

	/**
	 * @param string $filename
	 * @return Path
	 * @throws \Exception
	 */
	public final static function findSoursePath(string &$filename): Path {
		if (!isset(self::$Sources[$namespace = Regexp::create('/^([^:]+):/')
			->retrieve($filename, 1)])){

			if (!empty($namespace) || !isset(self::$Sources[self::DEFAULT_NAMESPACE])) {
				throw new \Exception(sprintf('Undefined namespace: %s!', $namespace));
			}

			return self::$Sources[self::DEFAULT_NAMESPACE]->toPath();
		}

		return self::$Sources[$namespace]->toPath();
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
	 * @const int
	 */
	public const CO_SKIP_RAW = 0b0001;

	/**
	 * @const int
	 */
	public const CO_SKIP_CALL = 0b0010;

	/**
	 * Initialize the standard compiler's behavior.
	 * @throws \Exception
	 */
	protected final static function initialize(): SInit {
		return new SInit([function($filepath){
			array_push(self::$History, $filepath);
		}], function(){
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
		});
	}

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

		if (~$mode & self::CO_SKIP_RAW) {
			yield (new Php())->minify(Path::create(dirname(__DIR__),
				'includes', 'prepared.php')->toFile()->getContent() . ' ?>');
		}

		if (~$mode & self::CO_SKIP_CALL) {
			yield '<?php ' . $name . '(__init(), $__data ?? []);?>';
		}
	}
}
