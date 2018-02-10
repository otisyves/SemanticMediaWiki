<?php

namespace SMW\Elastic\Exception;

use SMW\Utils\ErrorCodeFormatter;
use RuntimeException;


/**
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class JsonFormatException extends RuntimeException {

	/**
	 * @since 3.0
	 */
	public function __construct( $error, $content = '' ) {
		parent::__construct( ErrorCodeFormatter::getMessageFromJsonErrorCode( $error ) . " in $content" );
	}

}
