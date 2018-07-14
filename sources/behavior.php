<?php
namespace Able\Sabre\Standard;

use \Able\Sabre\Compiler;

use \Able\Sabre\Structures\SToken;
use \Able\Sabre\Structures\SState;
use \Able\Sabre\Structures\STrap;

use \Able\Sabre\Utilities\Queue;
use \Able\Sabre\Utilities\Task;

use \Able\IO\File;
use \Able\IO\Path;
use \Able\IO\WritingBuffer;

use \Able\Reglib\Regexp;
use \Able\Reglib\Reglib;

use \Able\Helpers\Str;
use \Able\Helpers\Arr;

/**
 * @param string $input
 * @return bool
 */
function checkFragmentSyntax(string $input): bool {
	try {
		return count(token_get_all('<?php '
			. trim($input) . ';', TOKEN_PARSE)) > 0;

	}catch (\Throwable $Exception){
		return false;
	}
}

/**
 * @param string $input
 * @return bool
 */
function checkArraySyntax(string $input): bool {
	try {
		$Info = array_slice(token_get_all('<?php ' . trim($input) . ';', TOKEN_PARSE), 1, -1);
	}catch (\Throwable $Exception){
		return false;
	}

	if (Arr::first($Info) !== '[' || Arr::last($Info) !== ']') {
		return false;
	}

	$count = 0;
	$size = count($Info);
	foreach ($Info as $token) {
		$size--;

		if (!is_array($token)) {
			if ($token == '[') {
				$count++;
			}
			if ($token == ']') {
				if ($count == 1){
					break;
				}

				$count--;
			}
		}
	}

	return $count && !$size;
}

/**
 * @param Path $Source
 * @param Path $Root
 * @param string $indent
 * @return WritingBuffer
 * @throws \Exception
 */
function involve(Path $Source, Path $Root, string $indent): WritingBuffer {
	($Buffer = new WritingBuffer())->write((new Compiler($Root))
		->compile($Source, Compiler::CM_NO_PREPARED));

	return $Buffer->process(function($source) use ($indent){
		return $indent . preg_replace('/(?:\n\r?)+/', '$0' . $indent, $source);
	});
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
	return 'if (' . $condition . '){';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::extend('if', new SToken('elseif', function (string $condition) {
	return '} elseif (' . $condition . ') {';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::extend('if', new SToken('else', function () {
	return '} else {';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('for', function (string $condition) {
	return 'for (' . $condition . '){';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('foreach', function (string $condition) {
	return 'foreach (' . $condition . '){';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('include', function (string $filename, Queue $Queue) {
	$Queue->immediately((new Path(trim($filename, '\'"')
		. '.sabre')), $Queue->indent());
}, 1, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('involve', function ($filename, $params, Queue $Queue) {
	if (!checkArraySyntax($params = preg_replace('/\s*,\s*/', ',', !is_null($params) ? $params : '[]'))){
		throw new \Exception('The assigned parameter is not an array!');
	}

	return 'function ' . ($name = 'f_' . md5($filename . $params)) .'($__data, $__global){ extract($__global);unset($__global);'
		. 'extract($__data);unset($__data); ?>' . "\n" . involve(new Path(trim($filename, '\'"') . '.sabre'), $Queue->getSourcePath(),
			$Queue->indent())->getContent() . "\n<?php } " . $name . "(" . $params . ", Arr::only(get_defined_vars(), g()));";
}, 2, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('param', function ($name, $value) {
	if (!preg_match('/\$' . Reglib::VAR. '/', $name)){
		throw new \Exception('Invalid variable name!');
	}

	if (!is_null($value) && !checkFragmentSyntax($value)){
		throw new \Exception('Invalid syntax!');
	}

	return 'if (!isset(' . $name . ')){ ' . $name . ' = '
		. (!is_null($value)? $value : 'null') . '; }';
}, 2, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('global', function ($name, $value) {
	if (!preg_match('/\$' . Reglib::VAR. '/', $name)){
		throw new \Exception('Invalid variable name!');
	}

	if (!is_null($value) && !checkFragmentSyntax($value)){
		throw new \Exception('Invalid syntax!');
	}

	return 'if (!isset(' . $name . ')){ ' . $name . ' = '
		. (!is_null($value)? $value : 'null') . '; }; g("' . substr($name, 1) . '");';
}, 2, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('set', function ($name) {
	if (!preg_match('/^' . Reglib::VAR. '$/', $name = substr($name, 1, -1))){
		throw new \Exception('Invalid flag name!');
	}

	return 'b("' . $name . '");';
}, 1, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('on', function (string $name) {
	if (!preg_match('/^' . Reglib::VAR. '$/', $name = trim($name, '\'"'))){
		throw new \Exception('Invalid flag name!');
	}

	return 'if (b("' . $name . '", "c")){';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('off', function (string $name) {
	if (!preg_match('/^' . Reglib::VAR. '$/', $name = trim($name, '\'"'))){
		throw new \Exception('Invalid flag name!');
	}

	return 'if (b("' . $name . '", "n")){';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('extends', function (string $name, Queue $Queue) {
	$Queue->add((new Path(trim($name, '\'"') . '.sabre')), $Queue->indent());
}, 1, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('section', function (string $name, Queue $Queue) {
	if (!preg_match('/^' . Reglib::VAR . '$/', $name = trim($name, '\'"'))){
		throw new \Exception('Invalid section name "' . $name. '"!');
	}

	return 's("' . $name . '", function ($__data, $__global){'
		. 'extract($__global);unset($__global);extract($__data);unset($__data);';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::finalize('section', new SToken('end', function () {
	return '}, f(get_defined_vars()), Arr::only(get_defined_vars(), g()));';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('yield', function (string $name, Queue $Queue) {
	return 's("' . trim($name, '\'"') . '");';
}, 1, false));

