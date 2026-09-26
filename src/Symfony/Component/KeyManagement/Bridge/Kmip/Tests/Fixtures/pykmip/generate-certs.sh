#!/usr/bin/env bash
set -euo pipefail

test "$#" -eq 1
out=$1
umask 077
mkdir -p "$out/certs" "$out/data" "$out/policies"

openssl req -x509 -newkey rsa:2048 -nodes -sha256 -days 2 -subj '/CN=KMIP Test CA' \
    -keyout "$out/certs/ca.key" -out "$out/certs/ca.crt" >/dev/null 2>&1

issue_cert() {
    local name=$1 cn=$2 usage=$3
    openssl req -newkey rsa:2048 -nodes -sha256 -subj "/CN=$cn" \
        -keyout "$out/certs/$name.key" -out "$out/certs/$name.csr" >/dev/null 2>&1
    if [[ -n $usage ]]; then
        printf 'subjectAltName=DNS:localhost\nextendedKeyUsage=%s\n' "$usage" > "$out/certs/$name.ext"
    else
        printf 'subjectAltName=DNS:localhost\n' > "$out/certs/$name.ext"
    fi
    openssl x509 -req -in "$out/certs/$name.csr" -CA "$out/certs/ca.crt" -CAkey "$out/certs/ca.key" \
        -CAcreateserial -days 2 -sha256 -extfile "$out/certs/$name.ext" -out "$out/certs/$name.crt" >/dev/null 2>&1
}

issue_cert server localhost serverAuth
issue_cert client client-one clientAuth
issue_cert other client-two clientAuth
issue_cert no-eku client-no-eku ''
openssl pkey -in "$out/certs/client.key" -aes256 -passout pass:test-passphrase \
    -out "$out/certs/client-encrypted.key" >/dev/null 2>&1

cat > "$out/server.conf" <<'CONF'
[server]
database_path=/kmip/data/pykmip.database
hostname=0.0.0.0
port=5696
certificate_path=/kmip/certs/server.crt
key_path=/kmip/certs/server.key
ca_path=/kmip/certs/ca.crt
auth_suite=TLS1.2
policy_path=/kmip/policies
enable_tls_client_auth=True
logging_level=INFO
CONF
