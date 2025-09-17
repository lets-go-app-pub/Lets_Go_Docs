<?php

//Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/../user-services-globals/user-services-globals.php';
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON;

/**
 * Sends an error to the mongoDB database.
*/
class HandleMongoDBErrors {

	const ERRORS_DATABASE_NAME = 'ERRORS';
	const FRESH_ERRORS_COLLECTION_NAME = 'FreshErrors';
	const HANDLED_ERRORS_LIST_COLLECTION_NAME = 'HandledErrorsList';

	const ERRORS_COLLECTION_ERROR_ORIGIN = 'eO';  //int32; follows ErrorOriginType enum inside ErrorOriginEnum.proto
	const ERRORS_COLLECTION_ERROR_URGENCY = 'eU';  //int32; follows ErrorUrgencyLevel enum inside ErrorOriginEnum.proto
	const ERRORS_COLLECTION_VERSION_NUMBER = 'vN';  //int32; version number (should be greater than 0)
	const ERRORS_COLLECTION_FILE_NAME = 'fN';  //string; file name where error occurred
	const ERRORS_COLLECTION_LINE_NUMBER = 'lN';  //int32; line number where error occurred (should be greater than 0)
	const ERRORS_COLLECTION_STACK_TRACE = 'sT';  //string; stack trace (if available) of error
	const ERRORS_COLLECTION_TIMESTAMP_STORED = 'tS';  //mongoDB Date; timestamp error was stored
	const ERRORS_COLLECTION_ERROR_MESSAGE = 'eM';  //string; error message

	const HANDLED_ERRORS_LIST_COLLECTION_ERROR_ORIGIN = 'eO';  //int32; follows ErrorOriginType enum inside ErrorOriginEnum.proto
	const HANDLED_ERRORS_LIST_COLLECTION_VERSION_NUMBER = 'vN';  //int32; version number (should be greater than 0)
	const HANDLED_ERRORS_LIST_COLLECTION_FILE_NAME = 'fN';  //string; file name where error occurred
	const HANDLED_ERRORS_LIST_COLLECTION_LINE_NUMBER = 'lN';  //int32; line number where error occurred (should be greater than 0)

	/**
	 * When the driver encodes a PHP integer to BSON (here), there is a macro that writes a 64-bit or 32-bit integer
	 * depending on the range of the PHP integer value. This is analogous to the server's behavior, where results
	 * of integer computation (e.g. from an $inc) will use the smallest type able to contain the value and roll over
	 * to the large type as needed. This means these 'small' values will be stored as int32 on the server.
	 */
	const WEB_SERVER_ERROR_ORIGIN_VALUE = 4;  //this is the value for ErrorOriginType::ERROR_ORIGIN_WEB_SERVER
	const WEB_SERVER_DEFAULT_URGENCY = 4;  //this is the value for ErrorUrgencyLevel::ERROR_URGENCY_LEVEL_MEDIUM

	function sendException( $exception, $lineNumber, $fileName ): void {

		$errorMessage = 'Exception thrown of type' . gettype($exception);

		if ( $exception instanceof Exception ) {
			$errorMessage = 'Wordpress error type \Exception: ' . $exception->getMessage();
		}

		$this->sendError( $errorMessage, $lineNumber, $fileName );
	}

	function sendError( $errorMessage, $lineNumber, $fileName ): void {

		$client = new Client();

		$errorsDB               = $client->{self::ERRORS_DATABASE_NAME};
		$freshErrorCollection   = $errorsDB->{self::FRESH_ERRORS_COLLECTION_NAME};
		$handledErrorCollection = $errorsDB->{self::HANDLED_ERRORS_LIST_COLLECTION_NAME};

		$checkIfErrorHandledQuery = [
			self::HANDLED_ERRORS_LIST_COLLECTION_ERROR_ORIGIN   => self::WEB_SERVER_ERROR_ORIGIN_VALUE,
			self::HANDLED_ERRORS_LIST_COLLECTION_VERSION_NUMBER => WEB_SERVER_VERSION_NUMBER,
			self::HANDLED_ERRORS_LIST_COLLECTION_FILE_NAME      => $fileName,
			self::HANDLED_ERRORS_LIST_COLLECTION_LINE_NUMBER    => $lineNumber
		];

		try {
			$errorHasBeenHandledDoc = $handledErrorCollection->findOne(
				$checkIfErrorHandledQuery,
				[ '_id' => 1 ]
			);

			if ( $errorHasBeenHandledDoc === null ) {
				return;
			}

			//NOTE: This method of getting the stack trace seems to be a bit prettier than debug_backtrace();
			$stackTraceException = new Exception;

			//NOTE: This SHOULD always use int32, however there is a schema in place just in case it
			// attempts to use an int64 somewhere.
			$freshErrorCollection->insertOne(
				[
					self::ERRORS_COLLECTION_ERROR_ORIGIN     => (string) self::WEB_SERVER_ERROR_ORIGIN_VALUE,
					self::ERRORS_COLLECTION_ERROR_URGENCY    => self::WEB_SERVER_DEFAULT_URGENCY,
					self::ERRORS_COLLECTION_VERSION_NUMBER   => WEB_SERVER_VERSION_NUMBER,
					self::ERRORS_COLLECTION_FILE_NAME        => $fileName,
					self::ERRORS_COLLECTION_LINE_NUMBER      => $lineNumber,
					self::ERRORS_COLLECTION_STACK_TRACE      => $stackTraceException->getTraceAsString(),
					self::ERRORS_COLLECTION_TIMESTAMP_STORED => new BSON\UTCDateTime( time() * 1000 ),
					self::ERRORS_COLLECTION_ERROR_MESSAGE    => $errorMessage
				]
			);

			//NOTE: if the variable
		} catch ( Exception $e ) { //exception calling a mongo function
			return;
		}
	}
}

$handleMongoDbErrors = new HandleMongoDBErrors();
