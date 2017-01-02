#!/bin/bash

docker login -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/json-parser quay.io/keboola/json-parser:$TRAVIS_TAG
docker images
docker push quay.io/keboola/json-parser:$TRAVIS_TAG
