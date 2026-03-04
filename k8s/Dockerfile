# Dockerfile
FROM nextcloud:32.0.5-apache

USER root

RUN apt update && \
    apt install -y curl ca-certificates && \
    install -d /usr/share/postgresql-common/pgdg && \
    curl -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc --fail https://www.postgresql.org/media/keys/ACCC4CF8.asc && \
    . /etc/os-release && \
    sh -c "echo 'deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt $VERSION_CODENAME-pgdg main' > /etc/apt/sources.list.d/pgdg.list"

RUN apt-get update -qq && \
    apt-get install -y -qq postgresql-client-17 && \
    rm -rf /var/lib/apt/lists/*

USER www-data
