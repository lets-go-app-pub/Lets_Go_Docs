rem proto_client_server_shared
protoc -I .\lets_go_proto\combination --grpc_out=.\Lets_Go_Interface\project\generated_proto_client_server_shared --plugin=protoc-gen-grpc=D:\vcpkg\packages\grpc_x64-windows\tools\grpc\grpc_cpp_plugin.exe ^
.\lets_go_proto\combination\AccessStatusEnum.proto ^
.\lets_go_proto\combination\AccountLoginTypeEnum.proto ^
.\lets_go_proto\combination\AccountState.proto ^
.\lets_go_proto\combination\AlgorithmSearchOptions.proto ^
.\lets_go_proto\combination\CategoryTimeFrame.proto ^
.\lets_go_proto\combination\ChatMessageStream.proto ^
.\lets_go_proto\combination\ChatMessageToClientMessage.proto ^
.\lets_go_proto\combination\ChatRoomCommands.proto ^
.\lets_go_proto\combination\ChatRoomInfoMessage.proto ^
.\lets_go_proto\combination\CreatedChatRoomInfo.proto ^
.\lets_go_proto\combination\EmailSendingMessages.proto ^
.\lets_go_proto\combination\ErrorMessage.proto ^
.\lets_go_proto\combination\ErrorOriginEnum.proto ^
.\lets_go_proto\combination\EventRequestMessage.proto ^
.\lets_go_proto\combination\FeedbackTypeEnum.proto ^
.\lets_go_proto\combination\FindMatches.proto ^
.\lets_go_proto\combination\LetsGoEventStatus.proto ^
.\lets_go_proto\combination\LoginFunction.proto ^
.\lets_go_proto\combination\LoginSupportFunctions.proto ^
.\lets_go_proto\combination\LoginToServerBasicInfo.proto ^
.\lets_go_proto\combination\LoginValuesToReturnToClient.proto ^
.\lets_go_proto\combination\MemberSharedInfoMessage.proto ^
.\lets_go_proto\combination\PreLoginTimestamps.proto ^
.\lets_go_proto\combination\ReportMessages.proto ^
.\lets_go_proto\combination\RequestFields.proto ^
.\lets_go_proto\combination\RequestMessages.proto ^
.\lets_go_proto\combination\RetrieveServerLoad.proto ^
.\lets_go_proto\combination\SendErrorToServer.proto ^
.\lets_go_proto\combination\SetFields.proto ^
.\lets_go_proto\combination\SMSVerification.proto ^
.\lets_go_proto\combination\StatusEnum.proto ^
.\lets_go_proto\combination\TypeOfChatMessage.proto ^
.\lets_go_proto\combination\UpdateOtherUserMessages.proto ^
.\lets_go_proto\combination\UserAccountType.proto ^
.\lets_go_proto\combination\UserEventCommands.proto ^
.\lets_go_proto\combination\UserMatchOptions.proto ^
.\lets_go_proto\combination\UserSubscriptionStatus.proto

protoc -I .\lets_go_proto\combination --cpp_out=.\Lets_Go_Interface\project\generated_proto_client_server_shared ^
.\lets_go_proto\combination\AccessStatusEnum.proto ^
.\lets_go_proto\combination\AccountLoginTypeEnum.proto ^
.\lets_go_proto\combination\AccountState.proto ^
.\lets_go_proto\combination\AlgorithmSearchOptions.proto ^
.\lets_go_proto\combination\CategoryTimeFrame.proto ^
.\lets_go_proto\combination\ChatMessageStream.proto ^
.\lets_go_proto\combination\ChatMessageToClientMessage.proto ^
.\lets_go_proto\combination\ChatRoomCommands.proto ^
.\lets_go_proto\combination\ChatRoomInfoMessage.proto ^
.\lets_go_proto\combination\CreatedChatRoomInfo.proto ^
.\lets_go_proto\combination\EmailSendingMessages.proto ^
.\lets_go_proto\combination\ErrorMessage.proto ^
.\lets_go_proto\combination\ErrorOriginEnum.proto ^
.\lets_go_proto\combination\EventRequestMessage.proto ^
.\lets_go_proto\combination\FeedbackTypeEnum.proto ^
.\lets_go_proto\combination\FindMatches.proto ^
.\lets_go_proto\combination\LetsGoEventStatus.proto ^
.\lets_go_proto\combination\LoginFunction.proto ^
.\lets_go_proto\combination\LoginSupportFunctions.proto ^
.\lets_go_proto\combination\LoginToServerBasicInfo.proto ^
.\lets_go_proto\combination\LoginValuesToReturnToClient.proto ^
.\lets_go_proto\combination\MemberSharedInfoMessage.proto ^
.\lets_go_proto\combination\PreLoginTimestamps.proto ^
.\lets_go_proto\combination\ReportMessages.proto ^
.\lets_go_proto\combination\RequestFields.proto ^
.\lets_go_proto\combination\RequestMessages.proto ^
.\lets_go_proto\combination\RetrieveServerLoad.proto ^
.\lets_go_proto\combination\SendErrorToServer.proto ^
.\lets_go_proto\combination\SetFields.proto ^
.\lets_go_proto\combination\SMSVerification.proto ^
.\lets_go_proto\combination\StatusEnum.proto ^
.\lets_go_proto\combination\TypeOfChatMessage.proto ^
.\lets_go_proto\combination\UpdateOtherUserMessages.proto ^
.\lets_go_proto\combination\UserAccountType.proto ^
.\lets_go_proto\combination\UserEventCommands.proto ^
.\lets_go_proto\combination\UserMatchOptions.proto ^
.\lets_go_proto\combination\UserSubscriptionStatus.proto

rem proto_server_specific
protoc -I .\lets_go_proto\combination --grpc_out=.\Lets_Go_Interface\project\generated_proto_server_specific --plugin=protoc-gen-grpc=D:\vcpkg\packages\grpc_x64-windows\tools\grpc\grpc_cpp_plugin.exe ^
.\lets_go_proto\combination\AccountCategoryEnum.proto ^
.\lets_go_proto\combination\AdminChatRoomCommands.proto ^
.\lets_go_proto\combination\AdminEventCommands.proto ^
.\lets_go_proto\combination\AdminLevelEnum.proto ^
.\lets_go_proto\combination\DisciplinaryActionType.proto ^
.\lets_go_proto\combination\ErrorHandledMoveReasonEnum.proto ^
.\lets_go_proto\combination\HandleErrors.proto ^
.\lets_go_proto\combination\HandleFeedback.proto ^
.\lets_go_proto\combination\HandleReports.proto ^
.\lets_go_proto\combination\ManageServerCommands.proto ^
.\lets_go_proto\combination\MatchTypeEnum.proto ^
.\lets_go_proto\combination\RequestAdminInfo.proto ^
.\lets_go_proto\combination\RequestStatistics.proto ^
.\lets_go_proto\combination\RequestUserAccountInfo.proto ^
.\lets_go_proto\combination\SendPictureForTesting.proto ^
.\lets_go_proto\combination\SetAdminFields.proto ^
.\lets_go_proto\combination\UserAccountStatusEnum.proto

protoc -I .\lets_go_proto\combination --cpp_out=.\Lets_Go_Interface\project\generated_proto_server_specific ^
.\lets_go_proto\combination\AccountCategoryEnum.proto ^
.\lets_go_proto\combination\AdminChatRoomCommands.proto ^
.\lets_go_proto\combination\AdminEventCommands.proto ^
.\lets_go_proto\combination\AdminLevelEnum.proto ^
.\lets_go_proto\combination\DisciplinaryActionType.proto ^
.\lets_go_proto\combination\ErrorHandledMoveReasonEnum.proto ^
.\lets_go_proto\combination\HandleErrors.proto ^
.\lets_go_proto\combination\HandleFeedback.proto ^
.\lets_go_proto\combination\HandleReports.proto ^
.\lets_go_proto\combination\ManageServerCommands.proto ^
.\lets_go_proto\combination\MatchTypeEnum.proto ^
.\lets_go_proto\combination\RequestAdminInfo.proto ^
.\lets_go_proto\combination\RequestStatistics.proto ^
.\lets_go_proto\combination\RequestUserAccountInfo.proto ^
.\lets_go_proto\combination\SendPictureForTesting.proto ^
.\lets_go_proto\combination\SetAdminFields.proto ^
.\lets_go_proto\combination\UserAccountStatusEnum.proto
