# generate a private key with the correct length
openssl genrsa -out grpc_private_key.key 2048

# optional: create a self-signed certificate
openssl req -config configuration.conf -extensions req_ext -new -x509 -key grpc_private_key.key -out grpc_public_cert.pem -days 73000

# generate private key
# openssl genrsa -out grpc_private_key.key 2048

# create certificate signing request (the .csr file)
# openssl req -new -out server.csr -key private_key.key -config configuration.conf 

# sign the certificate
# openssl x509 -req -days 73000 -in server.csr -signkey grpc_private_key.key -out grpc_public_key.crt -extensions req_ext -extfile configuration.conf

