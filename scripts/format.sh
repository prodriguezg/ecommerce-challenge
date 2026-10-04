#!/bin/sh
set -eu

services/commerce-api/vendor/bin/pint --format agent
services/payment-api/vendor/bin/pint --format agent
