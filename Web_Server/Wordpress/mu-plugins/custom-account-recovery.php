<?php

/**
 * Plugin Name: Custom Account Recovery
 * Description: Creates a shortcode to add to a page which connects to a remote mongoDB server and handles account recovery verification.
 *  Requires page slug to be 'account-recovery' for proper styling. Because this uses dynamic query variables it is by nature safe
 *  from csrf attacks.
 * Version: 1.0.0
 */

//Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/user-services-globals/user-services-globals.php';
require_once __DIR__ . '/handle-mongodb-errors/handle-mongodb-errors.php';
require_once __DIR__ . '/vendor/autoload.php';

//Include the stylesheet to make the form prettier.

use MongoDB\Client;
use MongoDB\BSON;
use function MongoDB\with_transaction;

/*
 This function will use PHONE_QUERY_VAR as the verification code. If the verification code is found and not expired, it
 will update the user email to 'verified'.
*/

class CustomAccountRecovery {

	//Regex explanation.
	//This regex is set up to only allow a limited number of characters. This way users cannot submit huge strings to
	// the server for processing.
	//This regex is set up to only take american phone numbers, the +1 is optional and the area code cannot start with
	// 0 or 1.
	//Extensions are currently not accepted.
	//This will need to be cleaned before use.
	// .{0,5}               Allows for any leading characters (possibly white space). Optional.
	// (?:\+?(\d{1,3}))?    The country code. Optional.
	// .{0,4}               Allows for up to four of any character between the country code and the area code. Optional.
	// (?!555)              Excludes 555 as an area code.
	// [2-9]{1}\d{2}        The area code. Mandatory.
	// .{0,4}               Allows for up to four of any character between the area code and exchange number. Optional.
	// \d{3}                The exchange number. Mandatory
	// .{0,4}               Allows for up to four of any character between the exchange number and subscriber number. Optional.
	// \d{4}                The subscriber number. Mandatory
	// .{0,4}               Allows for up to four of any character after the subscriber number (possibly white space). Optional.
	const TEXT_INPUT_REGEX_PATTERN = ".{0,5}(?:\+?(\d{1,3}))?.{0,4}(?!555)[2-9]{1}\d{2}.{0,4}\d{3}.{0,4}\d{4}.{0,4}";

	//This will not be shown generally because +1 will be inside the text input by default. However, it is the
	// equivalent of a hint for an Android EditText.
	//The message of 'placeholder' seems to sometimes be taken by 'title' (might be a browser specific issue). However,
	// if placeholder is NOT used, the title will NOT take its place and nothing will be shown.
	const TEXT_INPUT_PLACEHOLDER = '+1 XXX XXX XXXX';

	//This will be shown if the text input fails to match the regex pattern when submit is clicked. Also, it will
	// show if the text input is cleared. It is used as the equivalent of a hint for an Android EditText.
	const TEXT_INPUT_TITLE = 'Please enter phone number in the form ' . self::TEXT_INPUT_PLACEHOLDER . '.';

	const CURRENT_PHONE_NUMBER_INPUT_NAME = 'current_phone_number_input';
	const UPDATE_PHONE_NUMBER_INPUT_NAME = 'updated_phone_number_input';

	const ACCOUNT_RECOVERY_VERIFICATION_CODE_KEY = 'vC';
	const ACCOUNT_RECOVERY_PHONE_NUMBER_KEY = 'pN';
	const ACCOUNT_RECOVERY_TIME_VERIFICATION_CODE_GENERATED_KEY = 'tC';
	const ACCOUNT_RECOVERY_NUMBER_ATTEMPTS_KEY = 'nA';
	const ACCOUNT_RECOVERY_USER_ACCOUNT_OID_KEY = 'iD';

	const TIME_UNTIL_ACCOUNT_RECOVERY_EXPIRES_SECONDS = 2 * 60 * 60;

	const ACCOUNT_PHONE_NUMBER_KEY = 'sPn';
	const ACCOUNT_LAST_VERIFIED_TIME_KEY = 'dVt';
	const INFO_STORED_AFTER_DELETION_PHONE_NUMBER_KEY = 'pN';
	const PENDING_ACCOUNT_PHONE_NUMBER_KEY = 'pN';
	const USER_ACCOUNT_STATISTICS_PHONE_NUMBERS_KEY = 'pN'; //array of documents; documents contain
	const USER_ACCOUNT_STATISTICS_PHONE_NUMBERS_PHONE_NUMBER_KEY = 'l'; //array; array has 2 elements of type double, 1st is
	const USER_ACCOUNT_STATISTICS_ACCOUNT_RECOVERY_TIMES_KEY = 'rT'; //array of documents; documents contain
	const USER_ACCOUNT_STATISTICS_ACCOUNT_RECOVERY_TIMES_PREVIOUS_PHONE_NUMBER_KEY = 'p'; //string; previously used phone number
	const USER_ACCOUNT_STATISTICS_ACCOUNT_RECOVERY_TIMES_NEW_PHONE_NUMBER_KEY = 'n'; //string; updated phone number

    //MAXIMUM_NUMBER_ATTEMPTS_FOR_ACCOUNT_RECOVERY is used to prevent the user from entering
	// several invalid phone numbers in an attempt to guess the phone number for the PHONE_QUERY_VAR.
    // ACCOUNT_RECOVERY_NUMBER_ATTEMPTS_KEY is the value stored inside the database that it is compared to.
	// There are a few things to note from this.
    // 1) Do NOT want to update it when the user simply loads the document the first time or clicks the link. A
    //  database call will be made either way.
	// 2) The number must be checked when a POST fails. NOT when loading it from the database. This is because the
    //  error needs to be seen only after the user presses 'Submit'. If it is checked for when there is no error, it
    //  will return on the 'Submit' after the user is already out of attempts.
    // 3) This number must fail when MAXIMUM_NUMBER_ATTEMPTS_FOR_ACCOUNT_RECOVERY == ACCOUNT_RECOVERY_NUMBER_ATTEMPTS_KEY.
    //  This will give the proper number of attempts. However, possibly more importantly it will prevent people from
    //  spamming the server. The button will be disabled after the 'submit button' is clicked, so normal users will not
    //  be able to do this. So in order to spam the server, the user would need to manually program it in.
	const MAXIMUM_NUMBER_ATTEMPTS_FOR_ACCOUNT_RECOVERY = 5;

	//Giving vague error messages to potentially stop people from fishing for info. It shouldn't be a problem at the moment, but
	// as time moves on it may be.
	const GENERAL_ERROR_OCCURRED_RETRY_PROCESS_EXISTS_MESSAGE = 'Account recovery failed. Please retry process at a later time.';
	const VERIFICATION_CODE_HAS_EXPIRED_MESSAGE = 'Account recovery link has expired. Please retry process.';
	const TOO_MANY_ATTEMPTS_MADE_MESSAGE = 'Too many attempts have been made. Please retry account recovery process.';
	const PHONE_NUMBER_DOES_NOT_MATCH_MESSAGE = 'Current phone number does not match phone number on record.';
	const PHONE_NUMBER_ALREADY_EXISTS_MESSAGE = 'An account with this phone number already exists. Please login to that account or enter a different phone number.';
	const SUCCESSFULLY_UPDATED_MESSAGE = 'Phone number successfully updated! Please allow several minutes for changes to take effect.';

	function __construct() {

		add_shortcode( 'customAccountRecoveryCode', array( $this, 'runShortcode' ) );

		//This will enable the stylesheet.
		add_action( 'wp_enqueue_scripts', array( $this, 'setupStyleSheet' ) );

		//add_action( 'wp_head', array( $this, 'includeBootstrap' ) );
	}

	/*
	function includeBootstrap(): void {
		//NOTE: This function is no longer used, it was set up to download bootstrap (which is now brought in as a library below).
		// Leaving it here in case the code is needed again.
		if ( is_page( 'account-recovery' ) ) {
			?>
			<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
				  integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T"
				  crossorigin="anonymous">
			<?php
		}
	}
	*/

	function setupStyleSheet(): void {
		if ( is_page( 'account-recovery' ) ) {
			//Custom style sheet.
			wp_enqueue_style( 'account-recovery-stylesheet', plugin_dir_url( __FILE__ ) . '/styles/account-recovery-style.css' );

			//Downloaded bootstrap library.
			wp_enqueue_style( 'account-recovery-bootstrap-stylesheet', plugin_dir_url( __FILE__ ) . '/styles/http_cdn.jsdelivr.net_npm_bootstrap@4.3.1_dist_css_bootstrap.css' );
		}
	}

	//This function will return an empty string if an invalid phone number was passed.
	function validateAndFormatPhoneNumberString( $passedPhoneNumber ): string {
		$passedPhoneNumber = preg_filter( "/\D/", '', $passedPhoneNumber );

		if ( strlen( $passedPhoneNumber ) == 10 ) { //digits, no country code
			//Area code cannot be 555 or start with 0 or 1 (the regex for the input above also checks for this).
			if ( $passedPhoneNumber[0] == '1' 
				|| $passedPhoneNumber[0] == '0' 
				|| ($passedPhoneNumber[0] == '5'
   				   && $passedPhoneNumber[1] == '5'
				   && $passedPhoneNumber[2] == '5')
			) {
				return '';
			}

			return '+1' . $passedPhoneNumber;
		} elseif ( strlen( $passedPhoneNumber ) == 11 ) { //digits and country code

			//Country code must be 1 and area code cannot be 555 or start with 0 or 1 (the regex for the input above also
			// checks for this).
			if ( $passedPhoneNumber[0] != '1'
				|| $passedPhoneNumber[1] == '1'
				|| $passedPhoneNumber[1] == '0' 
				|| ($passedPhoneNumber[1] == '5'
                                   && $passedPhoneNumber[2] == '5'
                                   && $passedPhoneNumber[3] == '5')
			) {
				return '';
			}

			return '+' . $passedPhoneNumber;
		} else { //invalid phone number
			return '';
		}
	}

	function buildPhoneNumberTextInput(
		$startingValue,
		$label,
		$id,
		$name,
		$ariaDescribedBy
	): string {
		//NOTE: The inputs must be 'required' and have a 'pattern' in order to enforce the $_POST check below. It expects them to have values
		// any time they are submitted.
		ob_start();
		?>
        <div class="row">
            <div class="form-group col-sm-5">
                <label for="<?php echo $id; ?>"><?php echo $label; ?></label>
                <input type="tel" name="<?php echo $name; ?>" id="<?php echo $id; ?>" required="required"
                       class="form-control input-normal" aria-describedby="<?php echo $ariaDescribedBy; ?>"
                       value="<?php echo $startingValue; ?>"
                       placeholder="<?php echo self::TEXT_INPUT_PLACEHOLDER; ?>"
                       pattern="<?php echo self::TEXT_INPUT_REGEX_PATTERN; ?>"
                       title="<?php echo self::TEXT_INPUT_TITLE; ?>"/>
            </div>
        </div>
		<?php
		return ob_get_clean();
	}

	function buildForm(
		$currentPhoneNumber,
		$updatedPhoneNumber,
		$errorMessage
	): string {
		ob_start();
		?>
        <h4>Enter phone numbers below.</h4>
        <div class="container">

            <!-- The 'onsubmit' function prevents multiple form submissions. Made a question about it here.
             https://stackoverflow.com/questions/74899832/wordpress-stop-multiple-form-submission
             NOTE: This form can be submitted multiple times if a bad actor attempts it without causing anything
             incorrect to happen. However, it does not technically have server protection to provide a good
             user experience. -->
            <form method="POST" id="account-recovery-form" onsubmit="
                    document.getElementById('submit-button').disabled = true;
                    document.getElementById('submit-button').style.opacity='0.5';
                "
            >
				<?php
				echo $this->buildPhoneNumberTextInput( $currentPhoneNumber, 'Current Phone Number', 'current-phone-number', self::CURRENT_PHONE_NUMBER_INPUT_NAME, 'currentPhoneNumber' );
				echo $this->buildPhoneNumberTextInput( $updatedPhoneNumber, 'Updated Phone Number', 'updated-phone-number', self::UPDATE_PHONE_NUMBER_INPUT_NAME, 'updatedPhoneNumber' );
				?>
                <div class="row">
                    <button type="submit" class="btn btn-primary" id="submit-button">Submit</button>
                </div>
            </form>
            <div class="alert alert-warning" <?php if ( empty( $errorMessage ) ) {
				echo "hidden='hidden'";
			} ?>>
                <p id="error-message-paragraph"><?php echo $errorMessage; ?></p>
            </div>
        </div>
		<?php
		return ob_get_clean();
	}

	function deleteAccountRecoveryDocument(
		$accountRecoveryCollection,
		$verificationCode
	): void {
		global $handleMongoDbErrors;

		try {
			$accountRecoveryCollection->deleteOne(
				[
					self::ACCOUNT_RECOVERY_VERIFICATION_CODE_KEY => $verificationCode
				]
			);
		} catch ( Exception $exception ) { //exception calling a mongo function (catching only mongodb exceptions wasn't working properly).
			$handleMongoDbErrors->sendException( $exception, __LINE__, __FILE__ );
		}
	}

	//NOTE: This function will work properly for primitive types (String, Integer, Float, Boolean, Array) and objects
	// (which just means an instance of class). Anything else it will fail for.
	function accountRecoveryCheckObjectExistsAndType(
		$verificationCode,
		$accountRecoveryCollection,
		$extractedElement,
		string $elementExpectedType,
		string $nameForErrorMsg
	): bool {
		global $handleMongoDbErrors;

		if ( is_object( $extractedElement ) ) {
			$extractedElementType = get_class( $extractedElement );
		} else {
			$extractedElementType = gettype( $extractedElement );
		}

		if ( $extractedElement === null ) {
			$errorMessage = $nameForErrorMsg . " field does not exist.";
			$handleMongoDbErrors->sendError( $errorMessage, __LINE__, __FILE__ );
			$this->deleteAccountRecoveryDocument( $accountRecoveryCollection, $verificationCode );

			return false;
		} elseif ( $extractedElementType !== $elementExpectedType ) {
			$errorMessage = $nameForErrorMsg . " field is incorrect type\nExpected: " . $elementExpectedType . "\nReceived: " . $extractedElement;
			$handleMongoDbErrors->sendError( $errorMessage, __LINE__, __FILE__ );
			$this->deleteAccountRecoveryDocument( $accountRecoveryCollection, $verificationCode );

			return false;
		}

		return true;
	}

	function runShortcode(): string {

		//Bail out of the shortcode early. If this is not done, the script will run when the editor opens
		// a page containing this shortcode.
		if ( is_admin() ) {
			return '';
		}

		return $this->runAccountRecovery();
	}

	function runAccountRecovery(): string {

		global $handleMongoDbErrors;

		$inputCurrentPhoneNumber = '';
		$inputUpdatedPhoneNumber = '';

		$postMessage = ! empty( $_POST[ self::CURRENT_PHONE_NUMBER_INPUT_NAME ] ) && ! empty( $_POST[ self::UPDATE_PHONE_NUMBER_INPUT_NAME ] );

		if ( $postMessage ) {

			$inputCurrentPhoneNumber = $this->validateAndFormatPhoneNumberString( $_POST[ self::CURRENT_PHONE_NUMBER_INPUT_NAME ] );
			$inputUpdatedPhoneNumber = $this->validateAndFormatPhoneNumberString( $_POST[ self::UPDATE_PHONE_NUMBER_INPUT_NAME ] );

			//If invalid phone numbers entered, show an error message and send an error to server. These
			// checks should be checked for by the regex expression
			if ( empty( $inputCurrentPhoneNumber ) || empty( $inputUpdatedPhoneNumber ) ) {
				$errorMessage = 'The regex should not allow invalid phoneNumbers to be sent to the server.' .
				                "\nCurrentPhoneNumber: " . $inputCurrentPhoneNumber .
				                "\nUpdatedPhoneNumber: " . $inputUpdatedPhoneNumber;

				$handleMongoDbErrors->sendError( $errorMessage, __LINE__, __FILE__ );

				$errorMessage = 'Invalid phone numbers entered.';
				if ( ! empty( $inputCurrentPhoneNumber ) ) {
					$errorMessage = 'Invalid updated phone number entered.';
				} elseif ( ! empty( $inputUpdatedPhoneNumber ) ) {
					$errorMessage = 'Invalid current phone number entered.';
				}

				return $this->buildForm(
					$_POST[ self::CURRENT_PHONE_NUMBER_INPUT_NAME ],
					$_POST[ self::UPDATE_PHONE_NUMBER_INPUT_NAME ],
					$errorMessage
				);
			}
		}

		$client = new Client( MONGODB_URI_STRING );

		$verificationCode = get_query_var( PHONE_QUERY_VAR );

		//Make sure query variable is valid.
		if ( strlen( $verificationCode ) != EMAIL_VERIFICATION_AND_ACCOUNT_RECOVERY_VERIFICATION_CODE_LENGTH ) { //invalid query variable passed
			return buildMessageHeaderForMuPlugins( self::VERIFICATION_CODE_HAS_EXPIRED_MESSAGE );
		}

		//Must be defined, explained below where it is used.
		$ACCOUNTS_DATABASE_NAME           = ACCOUNTS_DATABASE_NAME;
		$ACCOUNT_RECOVERY_COLLECTION_NAME = ACCOUNT_RECOVERY_COLLECTION_NAME;

		//NOTE: These must be variables, this is because they will initialize as strings as well. For example
		// $db->TEST will open collection 'TEST'. So a global variable named TEST will be misinterpreted.
		$accountsDB                = $client->$ACCOUNTS_DATABASE_NAME;
		$accountRecoveryCollection = $accountsDB->$ACCOUNT_RECOVERY_COLLECTION_NAME;

		try {

			$queryDoc = [
				self::ACCOUNT_RECOVERY_VERIFICATION_CODE_KEY => $verificationCode
			];

			//See MAXIMUM_NUMBER_ATTEMPTS_FOR_ACCOUNT_RECOVERY for more info on why it is only updated in one place.
			if ( $postMessage ) {
				$accountRecoveryDocument = $accountRecoveryCollection->findOneAndUpdate(
					$queryDoc,
					[
						'$inc' => [
							self::ACCOUNT_RECOVERY_NUMBER_ATTEMPTS_KEY => 1
						]
					],
					[
						'returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER
					]
				);
			} else {
				//See MAXIMUM_NUMBER_ATTEMPTS_FOR_ACCOUNT_RECOVERY for more info.
				$accountRecoveryDocument = $accountRecoveryCollection->findOne(
					$queryDoc
				);
			}

		} catch ( Exception $exception ) { //exception calling a mongo function (catching only mongodb exceptions wasn't working properly).
			$handleMongoDbErrors->sendException( $exception, __LINE__, __FILE__ );

			return buildMessageHeaderForMuPlugins( self::GENERAL_ERROR_OCCURRED_RETRY_PROCESS_EXISTS_MESSAGE );
		}

		if ( $accountRecoveryDocument === null ) { //Verification code not found.
			return buildMessageHeaderForMuPlugins( self::VERIFICATION_CODE_HAS_EXPIRED_MESSAGE );
		}

		$numberAttemptsAtAccountRecovery = $accountRecoveryDocument[ self::ACCOUNT_RECOVERY_NUMBER_ATTEMPTS_KEY ];

		//Make sure number of attempts variable is integer type.
		if ( ! $this->accountRecoveryCheckObjectExistsAndType(
			$verificationCode,
			$accountRecoveryCollection,
			$numberAttemptsAtAccountRecovery,
			'integer',
			"verification_code_generated_time"
		) ) {
			return buildMessageHeaderForMuPlugins( self::GENERAL_ERROR_OCCURRED_RETRY_PROCESS_EXISTS_MESSAGE );
		}

		if ( $numberAttemptsAtAccountRecovery > self::MAXIMUM_NUMBER_ATTEMPTS_FOR_ACCOUNT_RECOVERY ) {
			//This is a fail-safe for spamming the Submit button. See when $_SESSION is set up earlier for more info
			// on multiple submits.
			//See MAXIMUM_NUMBER_ATTEMPTS_FOR_ACCOUNT_RECOVERY for more info.
			return buildMessageHeaderForMuPlugins( self::GENERAL_ERROR_OCCURRED_RETRY_PROCESS_EXISTS_MESSAGE );
		}

		$verificationCodeGeneratedTime = $accountRecoveryDocument[ self::ACCOUNT_RECOVERY_TIME_VERIFICATION_CODE_GENERATED_KEY ];

		if ( ! $this->accountRecoveryCheckObjectExistsAndType(
			$verificationCode,
			$accountRecoveryCollection,
			$verificationCodeGeneratedTime,
			BSON\UTCDateTime::class,
			"verification_code_generated_time"
		) ) {
			return buildMessageHeaderForMuPlugins( self::GENERAL_ERROR_OCCURRED_RETRY_PROCESS_EXISTS_MESSAGE );
		}

		$currentTimeSeconds    = time();
		$expirationTimeSeconds = $verificationCodeGeneratedTime->toDateTime()->getTimestamp() + self::TIME_UNTIL_ACCOUNT_RECOVERY_EXPIRES_SECONDS;

		if ( $currentTimeSeconds > $expirationTimeSeconds ) { //time is expired
			$this->deleteAccountRecoveryDocument( $accountRecoveryCollection, $verificationCode );

			return buildMessageHeaderForMuPlugins( self::VERIFICATION_CODE_HAS_EXPIRED_MESSAGE );
		} elseif ( ! $postMessage ) { //initial request
			return $this->buildForm( '+1 ', '+1 ', '' );
		}

		$userAccountPhoneNumber = $accountRecoveryDocument[ self::ACCOUNT_RECOVERY_PHONE_NUMBER_KEY ];

		//Make sure phone number is string type.
		if ( ! $this->accountRecoveryCheckObjectExistsAndType(
			$verificationCode,
			$accountRecoveryCollection,
			$userAccountPhoneNumber,
			'string',
			"verification_code_generated_time"
		) ) {
			return buildMessageHeaderForMuPlugins( self::GENERAL_ERROR_OCCURRED_RETRY_PROCESS_EXISTS_MESSAGE );
		}

		if ( $userAccountPhoneNumber != $inputCurrentPhoneNumber ) {
			//See MAXIMUM_NUMBER_ATTEMPTS_FOR_ACCOUNT_RECOVERY for more info.
			if ( $numberAttemptsAtAccountRecovery >= self::MAXIMUM_NUMBER_ATTEMPTS_FOR_ACCOUNT_RECOVERY ) {
				$this->deleteAccountRecoveryDocument( $accountRecoveryCollection, $verificationCode );

				return buildMessageHeaderForMuPlugins( self::TOO_MANY_ATTEMPTS_MADE_MESSAGE );
			}

			return $this->buildForm( $inputCurrentPhoneNumber, $inputUpdatedPhoneNumber, self::PHONE_NUMBER_DOES_NOT_MATCH_MESSAGE );
		}

		$returnString        = self::GENERAL_ERROR_OCCURRED_RETRY_PROCESS_EXISTS_MESSAGE;
		$successfullyUpdated = false;

		$transactionCallback = function ( MongoDB\Driver\Session $session ) use (
			$accountsDB,
			$accountRecoveryCollection,
			$handleMongoDbErrors,
			$accountRecoveryDocument,
			$numberAttemptsAtAccountRecovery,
			$verificationCode,
			$userAccountPhoneNumber,
			$inputCurrentPhoneNumber,
			$inputUpdatedPhoneNumber,
			&$returnString,
			&$successfullyUpdated
		): void {

			//NOTE: Do not catch exceptions, the session must catch them. The exclusion to this rule is if the session
			// is not passed into the database command in the form of [ 'session' => $session ]. This can happen if
			// an error needs to be stored or the account recovery document needs to be deleted.

			$USER_ACCOUNTS_COLLECTION_NAME              = USER_ACCOUNTS_COLLECTION_NAME;
			$INFO_STORED_AFTER_DELETION_COLLECTION_NAME = INFO_STORED_AFTER_DELETION_COLLECTION_NAME;
			$PENDING_ACCOUNT_COLLECTION_NAME            = PENDING_ACCOUNT_COLLECTION_NAME;

			//NOTE: This must be a variable, this is because it will initialize as a string as well. For example,
			// $db->TEST will open collection 'TEST'. So a global variable named TEST will be misinterpreted.
			$userAccountCollection = $accountsDB->$USER_ACCOUNTS_COLLECTION_NAME;

			$updatedPhoneNumberDocCount = $userAccountCollection->countDocuments(
				[ self::ACCOUNT_PHONE_NUMBER_KEY => $inputUpdatedPhoneNumber ],
				[ 'session' => $session ]
			);

			if ( $updatedPhoneNumberDocCount > 0 ) {
				//See MAXIMUM_NUMBER_ATTEMPTS_FOR_ACCOUNT_RECOVERY for more info.
				if ( $numberAttemptsAtAccountRecovery >= self::MAXIMUM_NUMBER_ATTEMPTS_FOR_ACCOUNT_RECOVERY ) {
					$this->deleteAccountRecoveryDocument( $accountRecoveryCollection, $verificationCode );

					$returnString = buildMessageHeaderForMuPlugins( self::TOO_MANY_ATTEMPTS_MADE_MESSAGE );

					return;
				}

				$returnString = $this->buildForm( $inputCurrentPhoneNumber, $inputUpdatedPhoneNumber, self::PHONE_NUMBER_ALREADY_EXISTS_MESSAGE );

				return;
			}

			//A user account with the updated phone number does NOT already exist. Can continue

			//NOTE: It is possible that the user account being updated does NOT exist anymore (it was deleted for
			// example). However, still need to continue on with updating the separate info below.
			$userAccountCollection->updateOne(
				[ self::ACCOUNT_PHONE_NUMBER_KEY => $inputCurrentPhoneNumber ],
				[
					'$set' => [
						self::ACCOUNT_PHONE_NUMBER_KEY       => $inputUpdatedPhoneNumber,
						self::ACCOUNT_LAST_VERIFIED_TIME_KEY => new BSON\UTCDateTime( - 1 )
					]
				],
				[ 'session' => $session ]
			);

			$infoStoredAfterDeletionCollection = $accountsDB->$INFO_STORED_AFTER_DELETION_COLLECTION_NAME;

			$infoStoredAfterDeletionDocCount = $infoStoredAfterDeletionCollection->countDocuments(
				[ self::INFO_STORED_AFTER_DELETION_PHONE_NUMBER_KEY => $inputUpdatedPhoneNumber ],
				[ 'session' => $session ]
			);

			if ( $infoStoredAfterDeletionDocCount > 0 ) {
				//The updated phone number already has a separate info_stored_after_deletion account, need to update it.

				$infoStoredAfterDeletionDocument = $infoStoredAfterDeletionCollection->findOne(
					[ self::INFO_STORED_AFTER_DELETION_PHONE_NUMBER_KEY => $inputCurrentPhoneNumber ],
					[
						'projection' => [
							'_id'                                             => 0,
							self::INFO_STORED_AFTER_DELETION_PHONE_NUMBER_KEY => 0
						],
						'session'    => $session
					]
				);

				if ( $infoStoredAfterDeletionDocument === null ) {
					$errorMessage = 'A user account was found to exist without a INFO_STORED_AFTER_DELETION_COLLECTION.' .
					                "\nAccountPhoneNumber: " . $inputCurrentPhoneNumber .
					                "\nUpdatedPhoneNumber: " . $inputUpdatedPhoneNumber;

					$handleMongoDbErrors->sendError( $errorMessage, __LINE__, __FILE__ );
				} else {
					$infoStoredAfterDeletionCollection->updateOne(
						[ self::INFO_STORED_AFTER_DELETION_PHONE_NUMBER_KEY => $inputUpdatedPhoneNumber ],
						[
							'$set' => $infoStoredAfterDeletionDocument
						],
						[ 'session' => $session ]
					);

					$infoStoredAfterDeletionCollection->deleteOne(
						[ self::INFO_STORED_AFTER_DELETION_PHONE_NUMBER_KEY => $inputCurrentPhoneNumber ],
						[ 'session' => $session ]
					);
				}
			} else {
				//The updated phone number does not yet have a separate info_stored_after_deletion account, can simply
				// update old document.

				$infoStoredAfterDeletionCollection->updateOne(
					[ self::INFO_STORED_AFTER_DELETION_PHONE_NUMBER_KEY => $inputCurrentPhoneNumber ],
					[
						'$set' => [
							self::INFO_STORED_AFTER_DELETION_PHONE_NUMBER_KEY => $inputUpdatedPhoneNumber
						]
					],
					[ 'session' => $session ]
				);
			}

			$pendingAccountCollection = $accountsDB->$PENDING_ACCOUNT_COLLECTION_NAME;

			//NOTE: Pending accounts have a unique index by phone number, however deleting many here
			// so if that ever changes, this should be updated.
			$pendingAccountCollection->deleteMany(
				[
					self::PENDING_ACCOUNT_PHONE_NUMBER_KEY => [
						'$in' => [
							$inputCurrentPhoneNumber,
							$inputUpdatedPhoneNumber
						]
					]
				],
				[ 'session' => $session ]
			);

			//Account recovery document will be deleted after the session ends.

			$successfullyUpdated = true;
			$returnString        = buildMessageHeaderForMuPlugins( self::SUCCESSFULLY_UPDATED_MESSAGE );
		};

		$session = $client->startSession();

		try {
			with_transaction( $session, $transactionCallback );
		} catch ( Exception $exception ) {
			$handleMongoDbErrors->sendException( $exception, __LINE__, __FILE__ );
			$this->deleteAccountRecoveryDocument( $accountRecoveryCollection, $verificationCode );

			return buildMessageHeaderForMuPlugins( self::GENERAL_ERROR_OCCURRED_RETRY_PROCESS_EXISTS_MESSAGE );
		}

		if ( $successfullyUpdated ) {

			$this->deleteAccountRecoveryDocument( $accountRecoveryCollection, $verificationCode );

			$USER_ACCOUNT_STATISTICS_COLLECTION_NAME = USER_ACCOUNT_STATISTICS_COLLECTION_NAME;

			$userAccountStatisticsCollection = $accountsDB->$USER_ACCOUNT_STATISTICS_COLLECTION_NAME;

			$userAccountOid = $accountRecoveryDocument[ self::ACCOUNT_RECOVERY_USER_ACCOUNT_OID_KEY ];

			//NOTE: No need to show an error if something goes wrong here. The phone number was properly updated.
			if ( $this->accountRecoveryCheckObjectExistsAndType(
				$verificationCode,
				$accountRecoveryCollection,
				$userAccountOid,
				BSON\ObjectId::class,
				"verification_code_generated_time"
			) ) {
				$currentTimeMongoObject = new BSON\UTCDateTime( $currentTimeSeconds * 1000 );

				try {
					$userAccountStatisticsCollection->updateOne(
						[ '_id' => $userAccountOid ],
						[
							'$push' => [
								self::USER_ACCOUNT_STATISTICS_PHONE_NUMBERS_KEY          => [
									self::USER_ACCOUNT_STATISTICS_PHONE_NUMBERS_PHONE_NUMBER_KEY => $inputUpdatedPhoneNumber,
									USER_ACCOUNT_STATISTICS_DOCUMENT_TIMESTAMP_KEY               => $currentTimeMongoObject
								],
								self::USER_ACCOUNT_STATISTICS_ACCOUNT_RECOVERY_TIMES_KEY => [
									self::USER_ACCOUNT_STATISTICS_ACCOUNT_RECOVERY_TIMES_PREVIOUS_PHONE_NUMBER_KEY => $inputCurrentPhoneNumber,
									self::USER_ACCOUNT_STATISTICS_ACCOUNT_RECOVERY_TIMES_NEW_PHONE_NUMBER_KEY      => $inputUpdatedPhoneNumber,
									USER_ACCOUNT_STATISTICS_DOCUMENT_TIMESTAMP_KEY                                 => $currentTimeMongoObject
								]
							]
						]
					);
				} catch ( Exception $exception ) {
					$handleMongoDbErrors->sendException( $exception, __LINE__, __FILE__ );

					//Ok to continue here.
				}
			}
		}

		//NOTE: Shortcodes will show an error of 'Updating failed. The response is not a valid JSON response.' if
		// echo is used instead of return (only happens occasionally).
		return $returnString;

	}
}

$customAccountRecovery = new CustomAccountRecovery();
