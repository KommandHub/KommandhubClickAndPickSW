# syntax=docker/dockerfile:1

# shopware-cli, for validating and packaging this plugin from inside its own test
# container. Published as a purpose-built image holding nothing but the static Go
# binary (~50 MB, multi-arch), so copying the binary out is the leanest install:
# no apt source, no curl/gpg, no archive to clean up, a single layer. Pin a digest
# for a reproducible build:
#
#   docker compose build \
#     --build-arg SHOPWARE_CLI_IMAGE=shopware/shopware-cli:bin@sha256:<digest>
ARG SHOPWARE_CLI_IMAGE=shopware/shopware-cli:bin

FROM ${SHOPWARE_CLI_IMAGE} AS shopware-cli

FROM dockware/shopware:6.7.8.0

USER root

# pcov ships with the dockware image; phpunit.dist.xml turns it on at runtime via
# <ini name="pcov.enabled">, so no extension needs installing here. Coverage runs
# without Xdebug's overhead.

# /usr/local/bin is already on PATH for every user, so shopware-cli is callable
# directly. Fail the build (not the first `make validate-plugin`) if the binary
# is missing, not executable, or built for the wrong architecture.
COPY --from=shopware-cli /shopware-cli /usr/local/bin/shopware-cli
RUN chmod +x /usr/local/bin/shopware-cli && shopware-cli --version

# Restore the image's declared default user. It must be "dockware", not
# "www-data": they share uid 33, but dockware's entrypoint escalates with sudo
# and the sudo rights belong to the dockware account. Running as www-data (or
# root) can break boot with "sudo: a password is required".
USER dockware
