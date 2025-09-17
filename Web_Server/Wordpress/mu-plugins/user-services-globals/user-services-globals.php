<?php

//Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WEB_SERVER_VERSION_NUMBER                                        = 1;

//Query Var names.
const EMAIL_QUERY_VAR                                                  = 'emailVerificationCode';
const PHONE_QUERY_VAR                                                  = 'accountRecoveryCode';

const EMAIL_VERIFICATION_AND_ACCOUNT_RECOVERY_VERIFICATION_CODE_LENGTH = 30;
const MONGODB_URI_STRING                                               = 'mongodb://localhost:27017,localhost:27018,localhost:27019';

//Database
const ACCOUNTS_DATABASE_NAME                                           = 'Accounts';

//Collections
const USER_ACCOUNTS_COLLECTION_NAME                                    = 'user_accounts';
const PENDING_ACCOUNT_COLLECTION_NAME                                  = 'pending_account';
const INFO_STORED_AFTER_DELETION_COLLECTION_NAME                       = 'info_stored_after_delete';
const USER_ACCOUNT_STATISTICS_COLLECTION_NAME                          = 'user_account_stats';
const ACCOUNT_RECOVERY_COLLECTION_NAME                                 = 'AccountRecoveryInfo';
const EMAIL_VERIFICATION_COLLECTION_NAME                               = 'VerificationEmailInfo';

const USER_ACCOUNT_STATISTICS_DOCUMENT_TIMESTAMP_KEY                   = 't'; //mongodb Date; time the document was updated

function buildMessageHeaderForMuPlugins( $message ): string {
	ob_start();
	?>
    <h3><?php echo $message; ?></h3>
	<?php
	return ob_get_clean();
}