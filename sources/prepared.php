<?php
use \Able\Helpers\Arr;

function o($v){ return is_object($v) && method_exists($v, '__toString')
? $v->toString() : strval($v); }

function h($v){ return is_object($v) && method_exists($v, 'toHtml')
? $v->toHtml() : htmlspecialchars(o($v), ENT_QUOTES, "UTF-8", false); }

function f($v){ return array_filter($v, function ($s){
return !in_array($s, ['GLOBALS']) ; }, ARRAY_FILTER_USE_KEY); }

function b($n) { static $s = []; $n = strtolower($n); if (func_num_args() < 2 || func_get_arg(1) !== 'c') {
$s[$n] = true; } $r = isset($s[$n]) ? (bool)$s[$n] : false; return func_get_arg(1) !== 'n' ? $r : !$r; }

function s($n){ static $s = []; if (func_num_args() < 2) { if (isset($s[$n])) { foreach ($s[$n] as $o) {
echo $o; }}} else {if (!isset($s[$n])){ $s[$n] = []; } ob_start(); $l = ob_get_level(); call_user_func_array(func_get_arg(1),
array_slice(func_get_args(), 2)); while(ob_get_level() > $l){ ob_get_clean(); } array_push($s[$n], ob_get_clean());}}

function g(){ static $s = []; if (func_num_args() > 0){ array_push($s,
strtolower(func_get_arg(0))); } return $s; }
