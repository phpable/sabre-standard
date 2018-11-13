<?php
namespace Able\Sabre\Standard;

use \Able\Facades\AFacade;
use \Able\Facades\Structures\SInit;

use \Able\IO\File;
use \Able\IO\Path;
use \Able\IO\Abstractions\IReader;

use \Able\Reglib\Regex;

use \Able\Sabre\Compiler;
use \Able\Sabre\Structures\SInjection;
use \Able\Sabre\Structures\SCommand;

use \Able\Helpers\Str;
use \Able\Helpers\Arr;

use \Able\Minify\Php;

use \Exception;

/**
 * @method static void directive(string $token, callable $Handler)
 * @method static void injection(SInjection $Signature)
 * @method static void command(SCommand $Signature)
 * @method static void extend(string $token, SCommand $Signature)
 * @method static void finalize(string $token, SCommand $Signature)
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
	 * @throws Exception
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
	 * @throws Exception
	 */
	public final static function findSoursePath(string &$filename): Path {
		if (!isset(self::$Sources[$namespace = Regex::create('/^([^:]+):/')
			->retrieve($filename, 1)])){

			if (!empty($namespace) || !isset(self::$Sources[self::DEFAULT_NAMESPACE])) {
				throw new \Exception(sprintf('Undefined namespace: %s!', $namespace));
			}

			return self::$Sources[self::DEFAULT_NAMESPACE]->toPath();
		}

		return self::$Sources[$namespace]->toPath();
	}

	/**
	 * Initialize the standard compiler's behavior.
	 * @throws Exception
	 */
	protected final static function initialize(): SInit {
		return new SInit([], function(){
			try {
				if (!file_exists($Path = (new Path(__DIR__))->getParent()->append('includes', 'behavior.php'))) {
					throw new \Exception('Can not load behavior!');
				}

				include($Path->toString());
			}catch (\Throwable $Exception){

				throw new \ErrorException('Cannot load behavior: ' . $Exception->getMessage(), 0, 1,
					$Exception->getFile(), $Exception->getLine(), $Exception);
			}
		});
	}

	/**
	 * @param IReader $Reader
	 * @return \Generator
	 * @throws \Exception
	 */
	public static final function compile(IReader $Reader): \Generator {
		yield '<?php call_user_func(function($__obj, $__data){'
			. 'extract($__data); unset($__data); ?>';

		yield from parent::compile($Reader);

		yield '<?php }, call_user_func(function(){ ?>';

		yield (new Php())->minify(Path::create(dirname(__DIR__),
			'includes', 'prepared.php')->toFile()->getContent() . ' ?>');

		yield '<?php }), $__data);?>';
	}
}
