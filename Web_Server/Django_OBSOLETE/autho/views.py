from django.shortcuts import render
from autho import forms

# TODO: instead of implementing it this way I am changing it to have a TTL index and
# mongoDB will auto delete the account, will need to change this web page accordingly
# by making sure there is no case where the web page displays 'link has expired' OR always displays it somehow
# also planning on making the ACCOUNT_RECOVERY_TIME_CODE_EXPIRES_KEY a mongoDB Date Type object so no longer even
# need to extract it I don't think
# TODO: need to double check and make sure that the codes are still correct; also add notes in server saying to
# change them on django if changing them in server
# TODO: phone number needs to be updated in more than just verified account, at the very least seperate info account
# will also need it updated, but need to check for other instances as well
def account_recovery(request, verification_code=''):
    # NOTE: mongoDB requires UTF-8 encoding and the strings in python are by default stored as this
    ACCOUNT_RECOVERY_VERIFICATION_CODE_KEY = "vC"
    ACCOUNT_RECOVERY_TIME_CODE_EXPIRES_KEY = "tC"
    ACCOUNT_RECOVERY_VERIFIED_ACCOUNT_REFERENCE_KEY = "aR"

    # TODO: fix this
    ACCOUNT_RECOVERY_CODE_NUMBER_OF_DIGITS = 3

    VERIFIED_PHONE_NUMBER_KEY = "pN"
    VERIFIED_LAST_VERIFIED_TIME_KEY = "vT"

    ACCOUNTS_DATABASE_NAME = "Accounts"

    ACCOUNT_RECOVERY_COLLECTION_NAME = "AccountRecoveryInfo";
    VERIFIED_ACCOUNT_COLLECTION_NAME = "VerifiedAccounts"

    import pymongo
    import bson
    import inspect
    from autho.error_handling import handle_exception, handle_error
    from pymongo import MongoClient

    form = forms.GetPhoneNumberForm()

    client = MongoClient('localhost', 27017)

    accounts_db = client[ACCOUNTS_DATABASE_NAME]
    account_recovery_collection = accounts_db[ACCOUNT_RECOVERY_COLLECTION_NAME]

    page_location = 'autho_temps/error_page.html'
    message_dict = {'message': 'No authorization for this link exists.'}

    if len(verification_code) != ACCOUNT_RECOVERY_CODE_NUMBER_OF_DIGITS:
        return render(request, page_location, constext=message_dict)

    account_recovery_doc = None

    try:
        account_recovery_doc = account_recovery_collection.find_one({ACCOUNT_RECOVERY_VERIFICATION_CODE_KEY: str(verification_code)})
    except pymongo.errors.PyMongoError as err:
        handle_exception(err,
                        inspect.getlineno(inspect.currentframe()),
                        inspect.getfile(inspect.currentframe()))

    if account_recovery_doc: # if the document is found

        message_dict = {'message': 'Error, please re-try account retrieval process.'}

        verified_expiration_time = account_recovery_doc[ACCOUNT_RECOVERY_TIME_CODE_EXPIRES_KEY]

        if verified_expiration_time and type(verified_expiration_time) == bson.int64.Int64: # element exists and is correct type and
            import time
            current_time = int(time.time())

            if current_time > verified_expiration_time: # time is expired
                message_dict = {'message': 'Link has expired.'}

                try:
                    account_recovery_collection.delete_one({ACCOUNT_RECOVERY_VERIFICATION_CODE_KEY: str(verification_code)})
                except pymongo.errors.PyMongoError as err:
                    handle_exception(err,
                                    inspect.getlineno(inspect.currentframe()),
                                    inspect.getfile(inspect.currentframe()))
            elif request.method == 'POST': # time not expired and POST request
                # TODO: make sure phone number is valid
                verified_acct_oid = account_recovery_doc[ACCOUNT_RECOVERY_VERIFIED_ACCOUNT_REFERENCE_KEY]

                if verified_acct_oid and type(verified_acct_oid) == bson.objectid.ObjectId: # element exists and is object id type

                    verified_accounts_collection = accounts_db[VERIFIED_ACCOUNT_COLLECTION_NAME]
                    form = forms.GetPhoneNumberForm(request.POST)

                    if form.is_valid(): # Django form returned valid
                        phone_number_list = list(form.cleaned_data['phone_number'])
                        cleaned_phone_number_list = []
                        for i in phone_number_list:
                            if i.isdigit():
                                cleaned_phone_number_list += i

                        #+1 XXX XXX XXXX
                        valid_phone_number = False

                        print("phone number length: " + str(len(cleaned_phone_number_list)))

                        if len(cleaned_phone_number_list) == 11:
                            cleaned_phone_number_list.insert(0, '+')
                            valid_phone_number = True
                        elif len(cleaned_phone_number_list) == 10:
                            cleaned_phone_number_list.insert(0, '1')
                            cleaned_phone_number_list.insert(0, '+')
                            valid_phone_number = True

                        if valid_phone_number: # valid phone number entered
                            phone_number = "".join(cleaned_phone_number_list)
                            verified_account_exists = None

                            page_location = 'autho_temps/account_recovery_success.html'

                            try:
                                verified_account_exists = verified_accounts_collection.find_one({VERIFIED_PHONE_NUMBER_KEY: str(phone_number)})
                            except pymongo.errors.PyMongoError as err:
                                handle_exception(err,
                                                inspect.getlineno(inspect.currentframe()),
                                                inspect.getfile(inspect.currentframe()))
                            if verified_account_exists: # if account with gives phone number already exists
                                message_dict = {'form': form, 'message': 'An account with this phone number already exists. Please login to that account or enter a different number.'}
                                page_location = 'autho_temps/account_recovery.html'
                            else:
                                try:
                                    # need to make the account autho required
                                    verified_accounts_collection.update_one({"_id": verified_acct_oid},
                                                                            {'$set': {VERIFIED_PHONE_NUMBER_KEY: str(phone_number),
                                                                                      VERIFIED_LAST_VERIFIED_TIME_KEY: bson.int64.Int64(-1)}})

                                    account_recovery_collection.delete_one({ACCOUNT_RECOVERY_VERIFICATION_CODE_KEY: str(verification_code)})

                                    message_dict = {'message': 'Phone number successful updated!'}
                                    page_location = 'autho_temps/account_recovery_success.html'

                                except pymongo.errors.PyMongoError as err:
                                    handle_exception(err,
                                                    inspect.getlineno(inspect.currentframe()),
                                                    inspect.getfile(inspect.currentframe()))
                        else: # invalid phone number entered
                            message_dict = {'form': form, 'message': 'Invalid phone number entered'}
                            page_location = 'autho_temps/account_recovery.html'
                    else: # Django form returned invalid
                        error_message = "Django form returned invalid."
                        handle_error(error_message,
                                    inspect.getlineno(inspect.currentframe()),
                                    inspect.getfile(inspect.currentframe()))

                elif verified_acct_oid: # element exists and but is wrong type
                    error_message = "Verified account id reference incorrect type\nExpected: 'bson.objectid.ObjectId'\nRecieved: '" + str(type(verified_acct_oid)) + "'"
                    handle_error(error_message,
                                inspect.getlineno(inspect.currentframe()),
                                inspect.getfile(inspect.currentframe()))
                else: # element does not exist
                    error_message = "Verified account id reference does not exist."
                    handle_error(error_message,
                                inspect.getlineno(inspect.currentframe()),
                                inspect.getfile(inspect.currentframe()))
            else: # time not expired and GET request
                message_dict = {'form': form, 'message': ''}
                page_location = 'autho_temps/account_recovery.html'

        elif verified_expiration_time: # element exists and is wrong type and
            error_message = "Verified expiration time incorrect type\nExpected: 'bson.int64.Int64'\nRecieved: '" + str(type(verified_expiration_time)) + "'"
            handle_error(error_message,
                        inspect.getlineno(inspect.currentframe()),
                        inspect.getfile(inspect.currentframe()))

            try:
                account_recovery_collection.delete_one({ACCOUNT_RECOVERY_VERIFICATION_CODE_KEY: str(verification_code)})
            except pymongo.errors.PyMongoError as err:
                handle_exception(err,
                                inspect.getlineno(inspect.currentframe()),
                                inspect.getfile(inspect.currentframe()))
        else: # element does not exist
            error_message = "Verified expiration time does not exist."
            handle_error(error_message,
                        inspect.getlineno(inspect.currentframe()),
                        inspect.getfile(inspect.currentframe()))


    return render(request, page_location, context=message_dict)

# Create your views here.
def email_verification(request, verification_code=''):

    # NOTE: mongoDB requires UTF-8 encoding and the strings in python are by default stored as this
    EMAIL_VERIFICATION_VERIFICATION_CODE_KEY = "vC"
    EMAIL_VERIFICATION_TIME_CODE_EXPIRES_KEY = "tC"
    EMAIL_VERIFICATION_VERIFIED_ACCOUNT_REFERENCE_KEY = "aR"

    EMAIL_VERIFICATION_CODE_NUMBER_OF_DIGITS = 30

    VERIFIED_EMAIL_ADDRESS_REQUIRES_VERIFICATION_KEY = "eV"

    ACCOUNTS_DATABASE_NAME = "Accounts"

    EMAIL_VERIFICATION_COLLECTION_NAME = "VerificationEmailInfo"
    VERIFIED_ACCOUNT_COLLECTION_NAME = "VerifiedAccounts"

    import pymongo
    import bson
    import inspect
    from autho.error_handling import handle_exception, handle_error
    from pymongo import MongoClient

    client = MongoClient('localhost', 27017)

    accounts_db = client[ACCOUNTS_DATABASE_NAME]
    email_verification_collection = accounts_db[EMAIL_VERIFICATION_COLLECTION_NAME]

    message_dict = {'message': 'No authorization for this link exists.'}
    page_location = 'autho_temps/error_page.html'

    if len(verification_code) != EMAIL_VERIFICATION_CODE_NUMBER_OF_DIGITS:
        return render(request, page_location, context=message_dict)

    email_veri_doc = None

    try:
        email_veri_doc = email_verification_collection.find_one({EMAIL_VERIFICATION_VERIFICATION_CODE_KEY: str(verification_code)})
    except pymongo.errors.PyMongoError as err:
        handle_exception(err,
                        inspect.getlineno(inspect.currentframe()),
                        inspect.getfile(inspect.currentframe()))

    if email_veri_doc: # if the document is found

        message_dict = {'message': 'Error, please re-try email verification process.'}
        page_location = 'autho_temps/email_veri.html' # if it finds the document, display the proper page
        import time
        verified_expiration_time = email_veri_doc[EMAIL_VERIFICATION_TIME_CODE_EXPIRES_KEY]

        if verified_expiration_time and type(verified_expiration_time) == bson.int64.Int64: # element exists and is correct type and
            current_time = int(time.time())

            if current_time > verified_expiration_time: # time is expired
                message_dict = {'message': 'Verification link has expired, please re-try email verification process.'}
                try:
                    email_verification_collection.delete_one({EMAIL_VERIFICATION_VERIFICATION_CODE_KEY: str(verification_code)})
                except pymongo.errors.PyMongoError as err:
                    handle_exception(err,
                                    inspect.getlineno(inspect.currentframe()),
                                    inspect.getfile(inspect.currentframe()))
            else: # time not expired

                verified_acct_oid = email_veri_doc[EMAIL_VERIFICATION_VERIFIED_ACCOUNT_REFERENCE_KEY]

                if verified_acct_oid and type(verified_acct_oid) == bson.objectid.ObjectId: # element exists and is object id type

                    verified_accounts_collection = accounts_db[VERIFIED_ACCOUNT_COLLECTION_NAME]

                    try:
                        verified_accounts_collection.update_one({"_id": verified_acct_oid},
                                                                {'$set': {VERIFIED_EMAIL_ADDRESS_REQUIRES_VERIFICATION_KEY: False}})

                        email_verification_collection.delete_one({EMAIL_VERIFICATION_VERIFICATION_CODE_KEY: str(verification_code)})

                        message_dict = {'message': 'Email authorization successful!'}
                    except pymongo.errors.PyMongoError as err:
                        handle_exception(err,
                                        inspect.getlineno(inspect.currentframe()),
                                        inspect.getfile(inspect.currentframe()))
                elif verified_acct_oid: # element exists and but is wrong type
                    error_message = "Verified account id reference incorrect type\nExpected: 'bson.objectid.ObjectId'\nRecieved: '" + str(type(verified_acct_oid)) + "'"
                    handle_error(error_message,
                                inspect.getlineno(inspect.currentframe()),
                                inspect.getfile(inspect.currentframe()))
                else: # element does not exist
                    error_message = "Verified account id reference does not exist."
                    handle_error(error_message,
                                inspect.getlineno(inspect.currentframe()),
                                inspect.getfile(inspect.currentframe()))

        elif verified_expiration_time: # element exists and is wrong type and
            error_message = "Verified expiration time incorrect type\nExpected: 'bson.int64.Int64'\nRecieved: '" + str(type(verified_expiration_time)) + "'"
            handle_error(error_message,
                        inspect.getlineno(inspect.currentframe()),
                        inspect.getfile(inspect.currentframe()))

            try:
                email_verification_collection.delete_one({EMAIL_VERIFICATION_VERIFICATION_CODE_KEY: str(verification_code)})
            except pymongo.errors.PyMongoError as err:
                handle_exception(err,
                                inspect.getlineno(inspect.currentframe()),
                                inspect.getfile(inspect.currentframe()))
        else: # element does not exist
            error_message = "Verified expiration time does not exist."
            handle_error(error_message,
                        inspect.getlineno(inspect.currentframe()),
                        inspect.getfile(inspect.currentframe()))

    return render(request, page_location, context=message_dict)
