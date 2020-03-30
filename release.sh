#!/bin/bash

set -ex

VERSION=$(cat version)

sed -i "" -e "s/const SDK_VERSION = '[[:digit:]]*\.[[:digit:]]*\.[[:digit:]]*';/const SDK_VERSION = '$VERSION';/g" lib/Client.php

git add lib/*.php version
git commit -m "Updating Berbix PHP SDK version to $VERSION"
git tag -a $VERSION -m "Version $VERSION"
git push --follow-tags
