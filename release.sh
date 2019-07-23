#!/bin/bash

set -ex

VERSION=$(cat version)

sed -e "s/define('SDK_VERSION', '[[:digit:]]*\.[[:digit:]]*\.[[:digit:]]*');/define('SDK_VERSION', '$VERSION');/g" lib/Berbix.php

git add lib/Berbix.php version
git commit -m "Updating Berbix PHP SDK version to $VERSION"
git tag -a $VERSION -m "Version $VERSION"
git push --follow-tags
