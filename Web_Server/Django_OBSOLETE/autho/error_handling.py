
def handle_exception(exception, line_number, file_name):
    import pymongo
    from pymongo.errors import PyMongoError
    error_message = None

    if type(exception) == PyMongoError:
        error_message = "PyMongoError type exception\nException: " + exception.message
    else:
        error_message = "Exception thrown of type '" + type(exception) + "'."

    handle_error(error_message, line_number, file_name)

def handle_error(error_message, line_number, file_name):
    import pymongo
    from pymongo import MongoClient
    client = MongoClient()

    ERRORS_DATABASE_NAME = "ERRORS"
    WEB_SERVER_COLLECTION_NAME = "WebServer"

    errors_db = client[ERRORS_DATABASE_NAME]
    web_server_error_collection = errors_db[WEB_SERVER_COLLECTION_NAME]

    insert_doc = {'error_message': error_message,
                  'line_number': line_number,
                  'file_name': file_name}
    try:
        web_server_error_collection.insert_one(insert_doc)
    except pymongo.errors.PyMongoError as err:
        pass
