#!/bin/bash

cd site/docs || {
    echo "error: must be run from root of repository"
    exit 2
}

# rebuild the public/ folder
hugo || {
  echo "error: couldn't build 'public/'"
  exit 2
}

# push the public/ folder to the production server.
# note: it's "public/" not "public" - if we drop the slash, rsync will try
#       to create the folder on the remote server!
rsync -avz --delete public/ gamehub:/var/www/gamehub-docs/ || {
  echo "error: couldn't push 'public/'"
  exit 2
}

echo " info: rebuilt 'public/' and pushed to production server"
exit 0
