#!/bin/bash

docker run -td \
  --name openag_cove        openagdata/cove
docker run -td \
  --name openag_nerserver   openagdata/nerserver
docker run -td \
  --name openag_geocoder \
  --link openag_nerserver   openagdata/geocoder
docker run -td \
  --name openag_dportal \
  -p 8011:8011              openagdata/dportal
