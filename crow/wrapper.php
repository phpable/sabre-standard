<?php
call_user_func(function($__obj, $__data) {
	extract($__data);
	unset($__data);
	/*<%yield:content%>*/
}, call_user_func(function(){
	return /*<%yield:prepared%>*/;
}), $__data);
