<?php
namespace Able\Sabre\Standard;

use \Able\Facades\AFacade;

use \Able\Sabre\Utilities\Queue;
use \Able\Sabre\Utilities\Task;

use \Able\Sabre\Utilities\STrap;
use \Able\Sabre\Utilities\SToken;

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
}

/**
 * @param string $condition
 * @return string
 * @throws \Exception
 */
function findValidSectionName(string $condition): string {
	if (!preg_match('/^' . Reglib::VAR . '/', $name = substr((new Regexp('/^\(('
		. Reglib::QUOTED . ')\)$/'))->take($condition, 1), 1, -1))){
			throw new \Exception('Invalid section name "' . $name. '"!');
	}

	return $name;
}

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::prepend((new Path(__DIR__))->getParent()->append('prepared', 'standard.php')->toFile());

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::trap(new STrap('{{', '}}', function(string $condition){
	return '<?=e(' . trim($condition) . ');?>';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::trap(new STrap('{!!', '!!}', function(string $condition){
	return '<?=(' . trim($condition) . ');?>';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('if', function (string $condition) {
	return 'if ' . $condition . '{';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::extend('if', new SToken('elseif', function (string $condition) {
	return '} elseif ' . $condition . ' {';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::extend('if', new SToken('else', function (string $condition) {
	return '} else {';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('for', function (string $condition) {
	return 'for ' . $condition . '{';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('foreach', function (string $condition) {
	return 'foreach ' . $condition . '{';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('include', function (string $condition, Queue $Queue, Path $Path) {
	$Queue->inject((new Task($Path->append(substr($condition, 2,
		strlen($condition) - 4) . '.sabre')->toFile()->toReader()))->withPrefix($Queue->indent()));
}, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('param', function ($condition) {
	$Params = array_map(function(string $value){ return trim($value); }, preg_split('/,+/',
		substr($condition, 1, strlen($condition) - 2), 2, PREG_SPLIT_NO_EMPTY));

	if (!preg_match('/\$' . Reglib::VAR. '/', $Params[0])){
		throw new \Exception('Invalid parameter name!');
	}

	return 'if (!isset(' . $Params[0] . ')){ ' . $Params[0] . ' = '
		. (isset($Params[1]) ? $Params[1] : 'null') . '; }';
}, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('extends', function (string $condition, Queue $Queue, Path $Path) {
	if ($Queue->line() > 1){
		throw new \Exception('The template is already processing and cannot be extended!');
	}

	$Queue->inject((new Task($Path->append(substr($condition, 2,
		strlen($condition) - 4) . '.sabre')->toFile()->toReader()))->withPrefix($Queue->indent()));
}, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('section', function (string $condition, Queue $Queue, Path $Path) {
	static $i = 0;
	return 'function sabre_' . findValidSectionName($condition) . '_' . sprintf("%05d", ($i++)) . '($e){ extract($e); ';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('yield', function (string $condition, Queue $Queue, Path $Path) {
	return 'c("' . findValidSectionName($condition) . '", f(get_defined_vars()));';
}, false));

