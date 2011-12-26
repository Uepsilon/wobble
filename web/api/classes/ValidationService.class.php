<?php
class ValidationService {
	public static function validate_email($input) {
		ValidationService::check(!empty($input) && strpos($input, '@') > 0, 'Valid email adress required: ' . $input);
	}
	public static function validate_not_empty($input) {
		ValidationService::check(!empty($input));
	}

	public static function validate_boolean($input) {
		ValidationService::check ($input === true || $input === false || $input === 1 || $input === 0, 'Provide either true or false.');
	}
	
	public static function check($boolean, $message = 'Invalid Input!') {
		if ( !$boolean ) throw new Exception($message);
	}

	
}