# The same policy without the key read the AAD preflight needs.
path "transit/encrypt/*"           { capabilities = ["update"] }
path "transit/decrypt/*"           { capabilities = ["update"] }
path "transit/datakey/plaintext/*" { capabilities = ["update"] }
