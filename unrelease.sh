#!/bin/sh
VERSION="$1"
if [ "$VERSION" = "" ]; then
  echo "please provide a version as first parameter (e.g. 1.0.0)"
  exit 1  
fi
git tag -d "v$VERSION" && git push --delete origin "v$VERSION" 
