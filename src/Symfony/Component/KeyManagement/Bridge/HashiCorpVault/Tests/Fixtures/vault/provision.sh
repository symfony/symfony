#!/bin/sh
# Provisions a Vault the way an operator sets one up for production, before any application runs.
#
# Runs with the vault CLI and an operator token, e.g. inside the official image:
#     docker exec -e VAULT_TOKEN=root vault sh /fixtures/provision.sh
#
# The key material, the policies and the JWT signing key are test-only fixtures: never reuse them.
set -eu

FIXTURES=$(cd "$(dirname "$0")" && pwd)
export VAULT_ADDR="${VAULT_ADDR:-http://127.0.0.1:8200}"

if ! vault secrets list -format=json | grep -q '"transit/"'; then
    vault secrets enable transit
fi

# Keys are imported rather than generated, so that their material is known to the tests.
# Imported keys are neither exportable nor deletable unless asked for.
if ! vault read -format=json transit/keys/symfony-ci >/dev/null 2>&1; then
    vault transit import transit/keys/symfony-ci "@$FIXTURES/transit-key.b64" type=aes256-gcm96
fi
if ! vault read -format=json transit/keys/symfony-ci-derived >/dev/null 2>&1; then
    vault transit import transit/keys/symfony-ci-derived "@$FIXTURES/transit-key-derived.b64" type=aes256-gcm96 derived=true
fi

vault policy write symfony-kms "$FIXTURES/policy.hcl"
vault policy write symfony-kms-without-key-read "$FIXTURES/policy-without-key-read.hcl"

# The Kubernetes auth method reviews every login with the cluster's TokenReview API, which CI has not.
# The JWT auth method shares its login protocol and verifies signatures locally, so it is enabled at
# the "kubernetes" path for applications to log in exactly as they would in a cluster.
if ! vault auth list -format=json | grep -q '"kubernetes/"'; then
    vault auth enable -path=kubernetes jwt
fi
vault write auth/kubernetes/config jwt_validation_pubkeys="@$FIXTURES/jwt-signing-key.pub.pem"
vault write auth/kubernetes/role/symfony-app \
    role_type=jwt \
    bound_audiences=vault \
    user_claim=sub \
    token_policies=symfony-kms \
    token_no_default_policy=true \
    token_ttl=1h
