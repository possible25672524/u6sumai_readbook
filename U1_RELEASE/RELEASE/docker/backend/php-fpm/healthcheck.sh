#!/bin/sh
# backend/docker/php-fpm/healthcheck.sh
#
# Pings php-fpm's built-in /ping page directly over FastCGI using cgi-fcgi
# (package: libfcgi-bin). This checks that the FPM master + at least one
# worker process are alive and responding, independent of nginx.
#
# Used as the Docker HEALTHCHECK for the `backend` service.

set -e

RESPONSE=$(SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
    cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null || true)

case "$RESPONSE" in
    *pong*) exit 0 ;;
    *) exit 1 ;;
esac
