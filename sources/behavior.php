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

/** @noinspection PhpUnhandledExceptionInspection */
//Compiler::prepend((new Path(__DIR__))->append('prepared.php')->toFile());

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::hook('{{--', function(Queue $Queue, SState $SState){
	$SState->ignore = true;
});

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::hook('--}}', function(Queue $Queue, SState $SState){
	$SState->ignore = false;
});

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::hook('{{##', function(Queue $Queue, SState $SState){
	$SState->verbatim = true;
});

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::hook('##}}', function(Queue $Queue, SState $SState){
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
	return '<?php if (' . $condition . '){?>';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::extend('if', new SToken('elseif', function (string $condition) {
	return '<?php } elseif (' . $condition . ') {?>';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::extend('if', new SToken('else', function () {
	return '<?php } else {?>';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('for', function (string $condition) {
	return '<?php for (' . $condition . '){?>';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('foreach', function (string $condition) {
	return '<?php foreach (' . $condition . '){ ?>';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('include', function (string $filename, Queue $Queue) {
	$Queue->immediately((new Path(trim($filename, '\'"') . '.sabre')));
}, 1, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('involve', function ($filename, $params, Queue $Queue) {
	if (!checkArraySyntax($params = preg_replace('/\s*,\s*/', ',', !is_null($params) ? $params : '[]'))){
		throw new \Exception('The assigned parameter is not an array!');
	}

	($Buffer = new WritingBuffer())->write((new Compiler($Queue->getSourcePath()))
		->compile(new Path(trim($filename, '\'"') . '.sabre')));

	$Buffer->process(function(string $content) use ($filename, $params){
		return '<?php function ' . ($name = 'f_' . md5($filename . $params)) .'($__data, $__global){ extract($__global);unset($__global);'
			. 'extract($__data);unset($__data); ?>' . "\n" . $content . "\n<?php } " . $name . "(" . $params . ", Arr::only(get_defined_vars(), g())); ?>";
	});

	return $Buffer->toReadingBuffer();
}, 2, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('param', function ($name, $value) {
	if (!preg_match('/\$' . Reglib::VAR. '/', $name)){
		throw new \Exception('Invalid variable name!');
	}

	if (!is_null($value) && !checkFragmentSyntax($value)){
		throw new \Exception('Invalid syntax!');
	}

	return '<?php if (!isset(' . $name . ')){ ' . $name . ' = '
		. (!is_null($value)? $value : 'null') . '; }?>';
}, 2, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('global', function ($name, $value) {
	if (!preg_match('/\$' . Reglib::VAR. '/', $name)){
		throw new \Exception('Invalid variable name!');
	}

	if (!is_null($value) && !checkFragmentSyntax($value)){
		throw new \Exception('Invalid syntax!');
	}

	return '<?php if (!isset(' . $name . ')){ ' . $name . ' = '
		. (!is_null($value)? $value : 'null') . '; }; g("' . substr($name, 1) . '");?>';
}, 2, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('extends', function (string $name, Queue $Queue) {
	$Queue->add((new Path(trim($name, '\'"') . '.sabre')));
}, 1, false));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('section', function (string $name, Queue $Queue) {
	if (!preg_match('/^' . Reglib::VAR . '$/', $name = trim($name, '\'"'))){
		throw new \Exception('Invalid section name "' . $name. '"!');
	}

	return '<?php s("' . $name . '", function ($__data, $__global){'
		. 'extract($__global);unset($__global);extract($__data);unset($__data);?>';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::finalize('section', new SToken('end', function () {
	return '<?php }, f(get_defined_vars()), Arr::only(get_defined_vars(), g()));?>';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::token(new SToken('yield', function (string $name, Queue $Queue) {
	return '<?php s("' . trim($name, '\'"') . '"); ?>';
}, 1, false));

