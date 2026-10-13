# The policy an application gets in production, as documented in the bridge README.
path "transit/encrypt/*"           { capabilities = ["update"] }
path "transit/decrypt/*"           { capabilities = ["update"] }
path "transit/datakey/plaintext/*" { capabilities = ["update"] }
# only needed when AAD is used, to tell derived keys from non-derived ones
path "transit/keys/*"              { capabilities = ["read"] }
