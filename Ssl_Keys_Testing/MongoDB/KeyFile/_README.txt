Can get instructions on how to set up replica set here.
https://www.mongodb.com/docs/manual/tutorial/enforce-keyfile-access-control-in-existing-replica-set/

Commands used (note that the .txt extention and chmod command are both mandatory).
openssl rand -base64 756 > keyfile-mongodb.txt
chmod 400 keyfile-mongodb.txt

