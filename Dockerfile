################################################################################
################################################################################
###                                                                          ###
###                          Dockerfile                                     ###
###              ephemeralADMIN — PHP-FPM Container Definition              ###
###                                                                          ###
################################################################################
################################################################################

# PHP-FPM only — no bundled web server. Nginx (already running as the
# reverse proxy for ephemeral-rest) serves static assets directly and
# forwards *.php requests to this container over FastCGI on port 9000.
# See nginx.conf for the matching server block, and docker-compose.yml for
# how the two containers share the portal's code via the admin-app volume.
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# php-curl is the only non-core extension this portal needs (includes/api.php
# and includes/auth.php talk to the API entirely over curl). Everything else
# — sessions, JSON, DateTime — is already part of core PHP.
RUN apk add --no-cache curl-dev \
    && docker-php-ext-install curl

# Copy application code
COPY . .

# The portal has no writable state of its own (no uploads, no cache
# directory) — sessions use PHP-FPM's default in-container tmp storage,
# which is fine since the portal is stateless across restarts by design
# (session data loss just means an admin has to log in again).

EXPOSE 9000

CMD ["php-fpm"]