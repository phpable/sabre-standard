<?php
use \Able\Sabre\Compiler;

use \Able\Sabre\Structures\SToken;
use \Able\Sabre\Structures\SState;
use \Able\Sabre\Structures\STrap;

use \Able\Sabre\Utilities\Queue;
use \Able\Sabre\Utilities\Task;

use \Able\IO\File;
use \Able\IO\Path;

use \Able\Reglib\Regexp;
use \Able\Reglib\Reglib;

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
Compiler::prepend((new Path(__DIR__))->append('prepared.php')->toFile());

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::hook('{{--', function(Queue $Queue, SState $SState){
	$SState->ignore = true;
});

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::hook('--}}', function(Queue $Queue, SState $SState){
	$SState->ignore = false;
});

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::hook('#verbatim', function(Queue $Queue, SState $SState){
	$SState->verbatim = true;
});

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::hook('#off', function(Queue $Queue, SState $SState){
	$SState->verbatim = false;
});

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::trap(new STrap('{{', '}}', function(string $condition){
	return '<?=h(' . trim($condition) . ');?>';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::trap(new STrap('{!!', '!!}', function(string $condition){
	return '<?=o(' . trim($condition) . ');?>';
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
Compiler::token(new SToken('include', function (string $condition, Queue $Queue) {
	$Queue->immediately((new Path(substr($condition, 2,
		strlen($condition) - 4) . '.sabre')))->withPrefix($Queue->indent());
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
Compiler::token(new SToken('extends', function (string $condition, Queue $Queue) {
	$Queue->add((new Path(substr($condition, 2,
		strlen($condition) - 4) . '.sabre')))->withPrefix($Queue->indent());
}, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('section', function (string $condition, Queue $Queue) {
	static $i = 0;
	return 's("' . findValidSectionName($condition) . '", function ($e){ extract($e); ';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::finalize('section', new SToken('end', function () {
	return '}, f(get_defined_vars()));';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('yield', function (string $condition, Queue $Queue) {
	return 's("' . findValidSectionName($condition) . '");';
}, false));

