<?php
namespace Able\Sabre\Standard;

use \Able\Sabre\Compiler;
use \Able\Sabre\Standard\Delegate;
use \Able\Sabre\Parsers\BracketsParser;

use \Able\Sabre\Structures\SToken;
use \Able\Sabre\Structures\SState;
use \Able\Sabre\Structures\STrap;
use \Able\Sabre\Utilities\Queue;

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
 * @param string $source
 * @return array
 * @throws \Exception
 */
function parseObjectNotation(string &$source): array {
	return Arr::each(preg_split('/\s*,+\s*/', trim(substr(BracketsParser::parse($source,
		BracketsParser::BT_CURLY), 1, -1))), function ($key, $value){

		if (!preg_match('/^' . Reglib::VAR . '$/', $value)){
			throw new \Exception('Invalid property declaration!');
		}

		return $value;
	});
}

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::switch('{{--', function(Queue $Queue, SState $SState){
	$SState->ignore = true;
});

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::switch('--}}', function(Queue $Queue, SState $SState){
	$SState->ignore = false;
});

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::switch('{{##', function(Queue $Queue, SState $SState){
	$SState->verbatim = true;
});

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::switch('##}}', function(Queue $Queue, SState $SState){
	$SState->verbatim = false;
});

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::trap(new STrap('{{', '}}', function(string $condition){
	return '<?=$__obj->h(' . trim($condition) . ');?>';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::trap(new STrap('{!!', '!!}', function(string $condition){
	return '<?=$__obj->o(' . trim($condition) . ');?>';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::token(new SToken('if', function (string $condition) {
	return '<?php if (' . $condition . '){?>';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::extend('if', new SToken('elseif', function (string $condition) {
	return '<?php } elseif (' . $condition . ') {?>';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::extend('if', new SToken('else', function () {
	return '<?php } else {?>';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::token(new SToken('for', function (string $condition) {
	return '<?php for (' . $condition . '){?>';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::token(new SToken('foreach', function (string $condition) {
	return '<?php foreach (' . $condition . '){ ?>';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::token(new SToken('declare', function ($name, $value) {
	if (!preg_match('/\$[A-Za-z][A-Za-z0-9_]/', $name)){
		throw new \Exception(sprintf("Invalid variable name: %s!", $name));
	}

	if (preg_match('/^\{[A-Za-z0-9_,\s]+\}$/', $value)){
		return '<?php if (!isset(' . $name . ')){ ' . $name . ' = new stdClass(); }' . ' foreach (json_decode(\''
			. json_encode(parseObjectNotation($value)) . '\', true) as $' . ($tmp = '_' . md5(implode([microtime(), $name]))) . '){'
				. 'if (!isset(' . $name . '->{$' . $tmp .'})){' . $name . '->{$' . $tmp . '} = null; }}?>';
	}

	if (!is_null($value) && !checkFragmentSyntax($value)){
		throw new \Exception('Invalid syntax!');
	}

	return '<?php if (!isset(' . $name . ')){ ' . $name . ' = '
		. (!is_null($value)? $value : 'null') . '; }?>';
}, 2, false));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::token(new SToken('set', function ($name, $value) {
	if (!preg_match('/\$[A-Za-z][A-Za-z0-9_]/', $name)){
		throw new \Exception(sprintf("Invalid variable name: %s!", $name));
	}

	if (!is_null($value) && !checkFragmentSyntax($value)){
		throw new \Exception('Invalid syntax!');
	}

	return '<?php ' . $name . ' = ' . (!is_null($value)? $value : 'null') . '; ?>';
}, 2, false));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::token(new SToken('include', function (string $filename, Queue $Queue) {
	$Queue->immediately(Delegate::findSoursePath($filename)->append($filename
		. '.sabre')->toFile()->toReader());
}, 1, false));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::token(new SToken('involve', function ($filename, $params, Queue $Queue, Compiler $Compiler) {
	if (!checkArraySyntax($params = preg_replace('/\s*,\s*/', ',', !is_null($params) ? $params : '[]'))){
		throw new \Exception('The assigned parameter нis not an array!');
	}

	($Buffer = new WritingBuffer())->write($Compiler->compile(Delegate::findSoursePath($filename)->append($filename
		. '.sabre')->toFile()->toReader()));

	return $Buffer->process(function($content) use ($filename, $params){
		return '<?php if(!function_exists("' . ($name = 'f_' . md5(implode([microtime(true), $filename, $params]))) . '")){ function ' .  $name . '($__data,$__export,$__obj){'
			. 'extract($__export);unset($__export);extract($__data);unset($__data); ?>' . "\n" . $content . "\n<?php }} " . $name . "(" . $params . ', $__obj->f(get_defined_vars()), $__obj); ?>';
	})->toReadingBuffer();
}, 2, false, true));


/** @noinspection PhpUnhandledExceptionInspection */
Delegate::token(new SToken('list', function ($dirname, $condition, $params, Queue $Queue, Compiler $Compiler) {
	if (!checkArraySyntax($params = preg_replace('/\s*,\s*/', ',', !is_null($params) ? $params : '[]'))){
		throw new \Exception('The assigned parameter is not an array!');
	}

	$Items = [];
	$Output = new WritingBuffer();

	foreach (Delegate::findSoursePath($dirname)->append($dirname)->toDirectory()
		->filter('*.sabre') as $Path){

			if (!$Path->isDot() && $Path->isFile()){
				$name = 'v_' . md5(implode([microtime(true), $Path->toString(), $params]));

				$Output->write(WritingBuffer::create($Compiler->compile($Path->toFile()->toReader()))->process(function($content) use ($name){
					return '<?php if(!function_exists("' . $name .'")){ function ' . $name . '($__data,$__export,$__obj){'
						. 'extract($__export);unset($__export);extract($__data);unset($__data); ?>' . "\n" . $content . "\n<?php }} ?>";
				})->toReadingBuffer()->read());

				$Items[RegExp::create('/\.sabre$/')
					->erase(basename($Path->toString()))] = $name;
			}
	}

	return $Output->process(function ($content) use ($condition, $Items, $params) {
		return $content .= "<?php switch (" . $condition . "){" . Str::join("\n", Arr::each($Items, function($name, $value) use ($params){
			return 'case "' . $name . '": ' . $value . '(' . $params . ', $__obj->f(get_defined_vars()), $__obj); break;';
		})). "} ?>"; })->toReadingBuffer();
}, 3, false, true));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::token(new SToken('extends', function (string $name, Queue $Queue) {
	$Queue->add(Delegate::findSoursePath($name)->append($name . '.sabre')->toFile()->toReader());
}, 1, false));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::token(new SToken('section', function (string $name, Queue $Queue) {
	if (!preg_match('/^' . Reglib::VAR . '$/', $name = trim($name, '\'"'))){
		throw new \Exception('Invalid section name "' . $name. '"!');
	}

	return '<?php $__obj->c("' . $name . '", function ($__data, $__obj){'
		. 'extract($__data);unset($__data);?>';
}, 1));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::finalize('section', new SToken('end', function () {
	return '<?php }, $__obj->f(get_defined_vars()), $__obj);?>';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Delegate::token(new SToken('yield', function (string $name, Queue $Queue) {
	return '<?php $__obj->c("' . $name . '"); ?>';
}, 1, false));
