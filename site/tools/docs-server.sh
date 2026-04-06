#!/bin/bash

cd site/docs || {
    echo "error: must be run from root of repository"
    exit 2
}

# load the site into the browser
open http://localhost:1313/

hugo server

exit 0
