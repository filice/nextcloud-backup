# Dockerfile
FROM nextcloud:31.0.7-apache
USER root
RUN apt-get update -qq && apt-get install -y -qq postgresql-client && rm -rf /var/lib/apt/lists/*
USER www-data
