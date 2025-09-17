
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
        AccessStatusEnum.proto
        AccountLoginTypeEnum.proto
        AccountState.proto
        AlgorithmSearchOptions.proto
        CategoryTimeFrame.proto
        ChatMessageStream.proto
        ChatMessageToClientMessage.proto
        ChatRoomCommands.proto
        ChatRoomInfoMessage.proto
        CreatedChatRoomInfo.proto
        EmailSendingMessages.proto
        ErrorMessage.proto
        ErrorOriginEnum.proto
        EventRequestMessage.proto
        FeedbackTypeEnum.proto
        FindMatches.proto
        LetsGoEventStatus.proto
        LoginFunction.proto
        LoginSupportFunctions.proto
        LoginToServerBasicInfo.proto
        LoginValuesToReturnToClient.proto
        MemberSharedInfoMessage.proto
        RetrieveServerLoad.proto
        PreLoginTimestamps.proto
        ReportMessages.proto
        RequestFields.proto
        RequestMessages.proto
        SendErrorToServer.proto
        SetFields.proto
        SMSVerification.proto
        StatusEnum.proto
        TypeOfChatMessage.proto
        UpdateOtherUserMessages.proto
        UserAccountType.proto
        UserEventCommands.proto
        UserMatchOptions.proto
        UserSubscriptionStatus.proto
        )

#
# Add Library target with protobuf sources
#
add_library(generated_proto_client_server_shared ${PROTO_FILES})

target_link_libraries(generated_proto_client_server_shared
    PUBLIC
        #protobuf::libprotoc
        protobuf::libprotobuf
        gRPC::grpc
        gRPC::grpc++
)
target_include_directories(generated_proto_client_server_shared
        PUBLIC
        ${CMAKE_CURRENT_BINARY_DIR}
        #"/usr/local/include/google/protobuf"
        )

#
# Compile protobuf and grpc files in generated_proto_client_server_shared target to cpp
#
get_target_property(grpc_cpp_plugin_location gRPC::grpc_cpp_plugin LOCATION)
protobuf_generate(
        TARGET generated_proto_client_server_shared
        LANGUAGE cpp
)

#If more PROTOC_OPTIONS are desired, it accepts multiple args just add more string to the end. Don't forget
# the -- before the arg.
protobuf_generate(
        TARGET generated_proto_client_server_shared
        LANGUAGE grpc
        GENERATE_EXTENSIONS .grpc.pb.h .grpc.pb.cc
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
