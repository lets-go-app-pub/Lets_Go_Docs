<?php

/**
 * Plugin Name: Custom Email Verification
 * Description: Creates a shortcode to add to a page which connects to a remote mongoDB server and handles email verification.
 *   Because this uses dynamic query variables it is by nature safe from csrf attacks.
 * Version: 1.0.0
 */

//Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/user-services-globals/user-services-globals.php';
require_once __DIR__ . '/handle-mongodb-errors/handle-mongodb-errors.php';
require_once __DIR__ . '/vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON;

/*
 This function will use EMAIL_QUERY_VAR as the verification code. If the verification code is found and not expired, it
 will update the user email to 'verified'.
*/

class CustomEmailVerification {

	const EMAIL_VERIFICATION_USER_ACCOUNT_REFERENCE_KEY = '_id';
	const EMAIL_VERIFICATION_VERIFICATION_CODE_KEY = 'vC';
	const EMAIL_VERIFICATION_TIME_VERIFICATION_CODE_GENERATED_KEY = 'tC';
	const EMAIL_VERIFICATION_ADDRESS_BEING_VERIFIED_KEY = 'eA';

	const ACCOUNT_EMAIL_ADDRESS_KEY = 'sEa';
	const ACCOUNT_EMAIL_ADDRESS_REQUIRES_VERIFICATION_KEY = 'bEv';
	const ACCOUNT_EMAIL_TIMESTAMP_KEY = 'dTe';
	const USER_ACCOUNT_STATISTICS_EMAILS_VERIFIED_KEY = 'eV';
	const USER_ACCOUNT_STATISTICS_EMAILS_VERIFIED_EMAIL_KEY = 'e'; //string; email address
	const TIME_UNTIL_EMAIL_VERIFICATION_EXPIRES_SECONDS = 2 * 60 * 60;

	//Giving vague error messages to potentially stop people from fishing for info. It shouldn't be a problem at the moment, but
	// as time moves on it may be.
	const EMAIL_VERIFICATION_ERROR_MESSAGE = 'An error has occurred. Please retry email verification process.';
	const VERIFICATION_LINK_EXPIRED = 'Email verification link has expired. Please retry process.';
	const EMAIL_ADDRESS_DOES_NOT_MATCH = 'Current email address does not match email address used for verification. Please retry email verification process.';
	const AUTHORIZATION_SUCCESSFUL = 'Email verification successful! Please allow several minutes for changes to be visible on your device.';

	function __construct() {
		add_shortcode( 'customEmailVerificationCode', array( $this, 'runShortcode' ) );
	}

	function runShortcode(): string {

		//Bail out of the shortcode early. If this is not done, the script will run when the editor opens
		// a page containing this shortcode.
		if ( is_admin() ) {
			return '';
		}

		//NOTE: Shortcodes will show an error of 'Updating failed. The response is not a valid JSON response.' if
		// echo is used instead of return (only happens occasionally).
		return buildMessageHeaderForMuPlugins( $this->runEmailVerification() );
	}

	function runEmailVerification(): string {
		$emailQueryVar = get_query_var( EMAIL_QUERY_VAR );

		if ( strlen( $emailQueryVar ) == EMAIL_VERIFICATION_AND_ACCOUNT_RECOVERY_VERIFICATION_CODE_LENGTH ) {
			return $this->validVerificationCodeLength( $emailQueryVar );
		} else { //Invalid query var passed
			//This is set to expired link to avoid giving addition information to potential bad actors.
			return self::VERIFICATION_LINK_EXPIRED;
		}
	}

	function validVerificationCodeLength( $emailQueryVar ): string {

		global $handleMongoDbErrors;

		$client = new Client( MONGODB_URI_STRING );

		//Must be defined, explained below where it is used.
		$ACCOUNTS_DATABASE_NAME             = ACCOUNTS_DATABASE_NAME;
		$EMAIL_VERIFICATION_COLLECTION_NAME = EMAIL_VERIFICATION_COLLECTION_NAME;

		//NOTE: These must be variables, this is because they will initialize as strings as well. For example
		// $db->TEST will open collection 'TEST'. So a global variable named TEST will be misinterpreted.
		$accountsDB                  = $client->$ACCOUNTS_DATABASE_NAME;
		$emailVerificationCollection = $accountsDB->$EMAIL_VERIFICATION_COLLECTION_NAME;

		try {
			return $this->findAndDeleteVerificationObject( $accountsDB, $emailVerificationCollection, $emailQueryVar );
		} catch ( Exception $exception ) { //exception calling a mongo function (catching only mongodb exceptions wasn't working properly).
			$handleMongoDbErrors->sendException( $exception, __LINE__, __FILE__ );

			return self::EMAIL_VERIFICATION_ERROR_MESSAGE;
		}
	}

	//Meant to be called inside a try catch block for mongoDB exceptions.
	function findAndDeleteVerificationObject( $accountsDB, $emailVerificationCollection, $emailQueryVar ): string {

		$emailVerificationDoc = $emailVerificationCollection->findOneAndDelete( [ self::EMAIL_VERIFICATION_VERIFICATION_CODE_KEY => $emailQueryVar ] );

		if ( $emailVerificationDoc !== null ) { //doc was found
			return $this->verificationDocumentFound( $accountsDB, $emailVerificationDoc );
		} else { //verification document was not found
			//This is set to expired link to avoid giving addition information to potential bad actors. This condition
			// is OK the verification doc could have been automatically removed by the system.
			return self::VERIFICATION_LINK_EXPIRED;
		}
	}

	function verificationDocumentFound( $accountsDB, $emailVerificationDoc ): string {
		global $handleMongoDbErrors;

		$verificationCodeCreatedTime = $emailVerificationDoc[ self::EMAIL_VERIFICATION_TIME_VERIFICATION_CODE_GENERATED_KEY ];

		if ( $verificationCodeCreatedTime instanceof BSON\UTCDateTime ) { //verification code time is correct type
			return $this->verificationCodeProperType( $accountsDB, $emailVerificationDoc, $verificationCodeCreatedTime );
		} else { //user account oid or email address is incorrect type
			$errorMessage = 'Invalid type found when getting verification code type: ' . getType( $verificationCodeCreatedTime );
			$handleMongoDbErrors->sendError( $errorMessage, __LINE__, __FILE__ );

			return self::EMAIL_VERIFICATION_ERROR_MESSAGE;
		}
	}

	function verificationCodeProperType( $accountsDB, $emailVerificationDoc, $verificationCodeCreatedTime ): string {
		//DateTime and time return seconds from Unix timestamp (not milliseconds).
		$currentTimeSeconds    = time();
		$expirationTimeSeconds = $verificationCodeCreatedTime->toDateTime()->getTimestamp() + self::TIME_UNTIL_EMAIL_VERIFICATION_EXPIRES_SECONDS;

		if ( $currentTimeSeconds <= $expirationTimeSeconds ) { //verification has not expired
			return $this->emailVerificationIsNotExpired( $accountsDB, $currentTimeSeconds, $emailVerificationDoc );
		} else { //verification code expired
			return self::VERIFICATION_LINK_EXPIRED;
		}
	}

	function emailVerificationIsNotExpired( $accountsDB, $currentTimeSeconds, $emailVerificationDoc ): string {
		global $handleMongoDbErrors;

		$userAccountOid        = $emailVerificationDoc[ self::EMAIL_VERIFICATION_USER_ACCOUNT_REFERENCE_KEY ];
		$extractedEmailAddress = $emailVerificationDoc[ self::EMAIL_VERIFICATION_ADDRESS_BEING_VERIFIED_KEY ];

		if ( $userAccountOid instanceof BSON\ObjectId
		     && is_string( $extractedEmailAddress ) ) { //user account oid & email address are correct type
			return $this->objectIdAndEmailAddressValid( $accountsDB, $userAccountOid, $extractedEmailAddress, $currentTimeSeconds );
		} else { //user account oid is incorrect type
			$errorMessage = "Invalid type found.\nuser account oid type: " . getType( $userAccountOid ) . "\nemail address type: " . gettype( $extractedEmailAddress );
			$handleMongoDbErrors->sendError( $errorMessage, __LINE__, __FILE__ );

			return self::EMAIL_VERIFICATION_ERROR_MESSAGE;
		}
	}

	//Meant to be called inside a try catch block for mongoDB exceptions.
	function objectIdAndEmailAddressValid( $accountsDB, $userAccountOid, $extractedEmailAddress, $currentTimeSeconds ): string {

		//Must be defined, explained below where it is used.
		$USER_ACCOUNTS_COLLECTION_NAME = USER_ACCOUNTS_COLLECTION_NAME;

		$userAccountsCollection   = $accountsDB->$USER_ACCOUNTS_COLLECTION_NAME;
		$mongoTypeDateCurrentTime = new BSON\UTCDateTime( $currentTimeSeconds * 1000 );

		$updateResult = $userAccountsCollection->updateOne(
			[
				'_id'                           => $userAccountOid,
				self::ACCOUNT_EMAIL_ADDRESS_KEY => $extractedEmailAddress
			],
			[
				'$set' => [
					self::ACCOUNT_EMAIL_ADDRESS_REQUIRES_VERIFICATION_KEY => false
				],
				'$max' => [
					self::ACCOUNT_EMAIL_TIMESTAMP_KEY => $mongoTypeDateCurrentTime
				]
			]
		);

		//The verification email address should always be removed when the account is deleted. This
		// means the _id should always return a result here. This upholds the assumption that the
		// email address does not match.
		//Matched count is used instead of modified count because it is possible for something odd
		// to happen and for this to NOT be modified. This is perfectly OK, simply continue.
		if ( $updateResult->getMatchedCount() == 1 ) { //email address matches

			if ( $updateResult->getModifiedCount() == 1 ) {
				//Only update the statistics document if the user account was updated.
				$this->updateUserAccountStatistics( $accountsDB, $userAccountOid, $extractedEmailAddress, $mongoTypeDateCurrentTime );
			}

			return self::AUTHORIZATION_SUCCESSFUL;
		} else { //email address does not match
			return self::EMAIL_ADDRESS_DOES_NOT_MATCH;
		}
	}

	//Meant to be called inside a try catch block for mongoDB exceptions.
	function updateUserAccountStatistics( $accountsDB, $userAccountOid, $extractedEmailAddress, $mongoTypeDateCurrentTime ): void {

		global $handleMongoDbErrors;
		$USER_ACCOUNT_STATISTICS_COLLECTION_NAME = USER_ACCOUNT_STATISTICS_COLLECTION_NAME;

		$userAccountStatisticsCollection = $accountsDB->$USER_ACCOUNT_STATISTICS_COLLECTION_NAME;

		$updateAccountStatisticsResult = $userAccountStatisticsCollection->updateOne(
			[ '_id' => $userAccountOid ],
			[
				'$push' => [
					self::USER_ACCOUNT_STATISTICS_EMAILS_VERIFIED_KEY => [
						self::USER_ACCOUNT_STATISTICS_EMAILS_VERIFIED_EMAIL_KEY => $extractedEmailAddress,
						USER_ACCOUNT_STATISTICS_DOCUMENT_TIMESTAMP_KEY          => $mongoTypeDateCurrentTime
					]
				]
			]
		);

		if ( $updateAccountStatisticsResult->getModifiedCount() < 0 ) {
			$errorMessage = "Failed to update account statistics.\n" . $updateAccountStatisticsResult;
			$handleMongoDbErrors->sendError( $errorMessage, __LINE__, __FILE__ );
			//OK to continue here, email address was properly verified.
		}
	}
}

$customEmailVerification = new CustomEmailVerification();