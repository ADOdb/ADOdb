#!/bin/sh

# I have no earthly idea why travis' YML parser blows up on this line
# and won't let me put it in the install section

echo '{ "postgres": { "user":"postgres", "password":"" }, "mysql": { "user":"root", "password":"" } }' > travis/credentials.json
