#!/bin/bash

cd site/blog || {
    echo "error: must run from the root of the repository"
    exit 2
}

# load the site into the browser
open http://localhost:1313/

hugo server

exit 0
