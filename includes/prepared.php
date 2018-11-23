<?php
if   (!function_exists('__init')) {
	function __init() {
		return new class {
			public function o($v) {
				return is_object($v) && method_exists($v, '__toString')
					? $v->__toString() : strval($v);
			}

			public function c($e) {
				return array_merge(isset($e['__export']) ? $e['__export']
					: [], $this->f($e));
			}

			public function h($v) {
				return is_object($v) && method_exists($v, 'toHtml')
					? $v->toHtml() : htmlspecialchars($this->o($v), ENT_QUOTES, "UTF-8", false);
			}

			public function f($v) {
				return array_filter($v, function ($s) {
					return !in_array($s, ['GLOBALS', '_POST', '_GET', '_FILES', '_SERVER',
						'_COOKIE', '__export', '__data']);
				}, ARRAY_FILTER_USE_KEY);
			}

			public function s($n) {
				static $s = [];
				if (func_num_args() < 2) {
					if (isset($s[$n])) {
						foreach ($s[$n] as $o) {
							echo $o;
						}
					}
				} else {
					if (!isset($s[$n])) {
						$s[$n] = [];
					}
					ob_start();
					$l = ob_get_level();
					call_user_func_array(func_get_arg(1),
						array_slice(func_get_args(), 2));
					while (ob_get_level() > $l) {
						ob_get_clean();
					}
					array_push($s[$n], ob_get_clean());
				}
			}
		};
	}
}
