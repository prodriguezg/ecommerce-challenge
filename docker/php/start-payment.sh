#!/bin/sh
set -eu

exec supervisord -c /etc/supervisor/conf.d/payment.conf
