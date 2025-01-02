#!/bin/sh
VERSION="$1"
if [ "$VERSION" = "" ]; then
  echo "please provide a version as first parameter (e.g. 1.0.0)"
  exit 1  
fi
git tag -a "v$VERSION" -m "release $VERSION" && git push origin --tags
