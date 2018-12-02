<?php
if (!function_exists('__init')) {
	function __init() {
		return new class {

			/**
			 * Converts an argument into a string.
			 *
			 * @param mixed $value
			 * @return string
			 */
			public function o($value) {
				return is_object($value) && method_exists($value, '__toString')
					? $value->__toString() : strval($value);
			}

			/**
			 * Converts an argument into a string and escapes HTML entities.
			 *
			 * @param mixed $value
			 * @return string
			 */
			public function h($value) {
				return is_object($value) && method_exists($value, 'toHtml')
					? $value->toHtml() : htmlentities($this->o($value), ENT_QUOTES, "UTF-8", false);
			}

			/**
			 * Filters an array by removing all special-using keys.
			 *
			 * @param array $value
			 * @return array
			 */
			public function f($value) {
				return is_array($value) ? (array_filter($value, function ($key) {
					return !in_array($key, ['GLOBALS', '_POST', '_GET', '_REQUEST', '_FILES',
						'_SESSION', '_SERVER', '_ENV', '_COOKIE']);
				}, ARRAY_FILTER_USE_KEY)) : [];
			}

			/**
			 * Collects section content returned by a handler
			 * or prints previously collected fragments if the second argument isn't specified.
			 *
			 * @param string $name
			 * @param callable handler
			 * @return void
			 */
			public function c($name,  $handler = null): void {
				static $Cache = [];

				if (is_null($handler)) {
					if (isset($Cache[$name])) {
						foreach ($Cache[$name] as $o) {
							echo $o;
						}

						unset($Cache[$name]);
					}
				} else {
					ob_start();
					$level = ob_get_level();

					call_user_func($handler, ...array_slice(func_get_args(), 2));
					while (ob_get_level() > $level) {
						ob_get_clean();
					}

					if (!isset($Cache[$name])) {
						$Cache[$name] = [];
					}

					array_push($Cache[$name], ob_get_clean());
				}
			}
		};
	}
}
