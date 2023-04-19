#!/bin/sh

openssl genrsa -out ca.key 2048
openssl req -x509 -new -nodes -key ca.key -days 146000 -subj '/CN=SymfonyMime CA/O=SymfonyMime/L=Paris/C=FR' -out ca.crt
openssl x509 -in ca.crt -clrtrust -out ca.crt

## Sign

openssl genrsa -out sign.key 2048
openssl req -new -key sign.key -subj '/CN=fabien@symfony.com/O=SymfonyMime/L=Paris/C=FR/emailAddress=fabien@symfony.com' -out sign.csr
openssl x509 -req -in sign.csr -CA ca.crt -CAkey ca.key -out sign.crt -days 146000 -addtrust emailProtection
openssl x509 -in sign.crt -clrtrust -out sign.crt

rm sign.csr

openssl genrsa -out intermediate.key 2048
openssl req -new -key intermediate.key -subj '/CN=SymfonyMime Intermediate/O=SymfonyMime/L=Paris/C=FR' -out intermediate.csr
openssl x509 -req -in intermediate.csr -CA ca.crt -CAkey ca.key -set_serial 01 -out intermediate.crt -days 146000
openssl x509 -in intermediate.crt -clrtrust -out intermediate.crt

rm intermediate.csr

openssl genrsa -out sign2.key 2048
openssl req -new -key sign2.key -subj '/CN=SymfonyMime-User2/O=SymfonyMime/L=Paris/C=FR' -out sign2.csr
openssl x509 -req -in sign2.csr -CA intermediate.crt -CAkey intermediate.key -set_serial 01 -out sign2.crt -days 146000 -addtrust emailProtection
openssl x509 -in sign2.crt -clrtrust -out sign2.crt

rm sign2.csr

### Sign with passphrase
openssl genrsa -aes256 -passout pass:symfony-rocks -out sign3.key 2048
openssl req -new -key sign3.key -passin pass:symfony-rocks -subj '/CN=SymfonyMime-User3/O=SymfonyMime/L=Paris/C=FR' -out sign3.csr
openssl x509 -req -in sign3.csr -CA ca.crt -CAkey ca.key -out sign3.crt -days 146000 -addtrust emailProtection
openssl x509 -in sign3.crt -clrtrust -out sign3.crt

rm sign3.csr

## Encrypt

openssl genrsa -out encrypt.key 2048
openssl req -new -key encrypt.key -subj '/CN=SymfonyMime-User/O=SymfonyMime/L=Paris/C=FR' -out encrypt.csr
openssl x509 -req -in encrypt.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out encrypt.crt -days 146000 -addtrust emailProtection
openssl x509 -in encrypt.crt -clrtrust -out encrypt.crt

rm encrypt.csr

openssl genrsa -out encrypt2.key 2048
openssl req -new -key encrypt2.key -subj '/CN=SymfonyMime-User2/O=SymfonyMime/L=Paris/C=FR' -out encrypt2.csr
openssl x509 -req -in encrypt2.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out encrypt2.crt -days 146000 -addtrust emailProtection
openssl x509 -in encrypt2.crt -clrtrust -out encrypt2.crt

rm encrypt2.csr
