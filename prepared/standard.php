<?php
function e($v){
	return htmlspecialchars($v, ENT_QUOTES, "UTF-8", false);
}

function c($n,$e){
	foreach(array_filter(get_defined_functions()["user"], function($value) use ($n,$e){
		return(preg_match('/^sabre_' . preg_quote($n, '/') . '_/', $value)); }) as $f){
			$f($e);
	}
}

function f($v){
	return array_filter($v, function ($s){
		return ($s = trim($s))[0] !== '_' && !in_array($s, ['GLOBALS']) ; }, ARRAY_FILTER_USE_KEY);
}
