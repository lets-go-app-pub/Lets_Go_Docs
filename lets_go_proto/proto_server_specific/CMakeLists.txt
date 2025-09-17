
#The path for this (CMAKE_PREFIX_PATH) is set inside Primary Application CMakeLists.txt
find_package(Protobuf 3.19.2 CONFIG REQUIRED)
find_package(gRPC 1.46.3 CONFIG REQUIRED)
#find_package(gRPC 1.47.0 CONFIG REQUIRED)
find_package(Threads)

#
# Protobuf/Grpc source files
#
#NOTE: do not use GLOB (snippet is below this NOTE) with this to extract all .proto files
# because the compiler will not detect when new files were added to/removed from
# the directory
#file(GLOB PROTO_FILES "*.proto")
set(PROTO_FILES
        AccountCategoryEnum.proto
        AdminChatRoomCommands.proto
        AdminEventCommands.proto
        AdminLevelEnum.proto
        DisciplinaryActionType.proto
        ErrorHandledMoveReasonEnum.proto
        HandleFeedback.proto
        HandleReports.proto
        ManageServerCommands.proto
        MatchTypeEnum.proto
        RequestAdminInfo.proto
        HandleErrors.proto
        RequestStatistics.proto
        RequestUserAccountInfo.proto
        SendPictureForTesting.proto
        SetAdminFields.proto
        UserAccountStatusEnum.proto
        )

#
# Add Library targets with protobuf sources
# NOTE: generated_proto_client_server_shared must be added. This is because while the IMPORT_DIRS are included below
#  c++ itself will attempt to link the libraries together after the files are generated. When
#  generated_proto_client_server_shared is added here, then it will force that library to be compiled first.
add_library(generated_proto_server_specific ${PROTO_FILES})
target_link_libraries(generated_proto_server_specific
    PUBLIC
        generated_proto_client_server_shared
        protobuf::libprotobuf
        gRPC::grpc
        gRPC::grpc++
)

# SERVER_SPECIFIC_PROTO_INCLUDE_PATHS is the include path so that the proto_client_server_shared .grpc.pb.h
#  can be included in the generated files
if(NOT DEFINED SERVER_SPECIFIC_PROTO_INCLUDE_PATHS)
    message(FATAL_ERROR "You must set SERVER_SPECIFIC_PROTO_INCLUDE_PATHS environment variable for proto_server_specific to compile properly")
endif()

target_include_directories(
        generated_proto_server_specific
        PUBLIC
        ${CMAKE_CURRENT_BINARY_DIR}
        ${SERVER_SPECIFIC_PROTO_INCLUDE_PATHS}
)

set(PROTOBUF_GENERATE_IMPORT_DIRS
        $ENV{CLIENT_SERVER_PROTO_FILE_DIRECTORY_LETS_GO}
        $ENV{SERVER_SPECIFIC_PROTO_FILE_DIRECTORY_LETS_GO}
        )

#
# Compile protobuf and grpc files in generated_proto_server_specific target to cpp
#
get_target_property(grpc_cpp_plugin_location gRPC::grpc_cpp_plugin LOCATION)
protobuf_generate(
        TARGET generated_proto_server_specific
        LANGUAGE cpp
        IMPORT_DIRS ${PROTOBUF_GENERATE_IMPORT_DIRS}
)

#If more PROTOC_OPTIONS are desired, it accepts multiple args just add more string to the end. Don't forget
# the -- before the arg.
protobuf_generate(
        TARGET generated_proto_server_specific
        LANGUAGE grpc
        GENERATE_EXTENSIONS .grpc.pb.h .grpc.pb.cc
        IMPORT_DIRS ${PROTOBUF_GENERATE_IMPORT_DIRS}
        PLUGIN "protoc-gen-grpc=${grpc_cpp_plugin_location}"
        # PROTOC_OPTIONS "--grpc_out=generate_mock_code=true:."
)

#NOTE: leaving this here to show additional parameters
#NOTE: remember that the 'import' command for proto3 is relative to where the protoc command was
# run from while the 'import' command inside java is relative to the package
#    protobuf_generate(
#            LANGUAGE cpp
#            TARGET proto_files
#            PROTOC_OUT_DIR "/home/jeremiah/CLionProjects/MongoDbTest/cmake-build-debug"
#            IMPORT_DIRS "/home/jeremiah/ProtocolBuffers/LetsGo"
#            PROTOS ${proto}
#    )


