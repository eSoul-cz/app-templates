# syntax=docker/dockerfile:1
# Image of a static site with a CMS (framework/astro/deployment.md, "Site with a CMS: image to Komodo"). It is only the container the
# assets travel in: the final stage is `FROM scratch` and holds the VERIFIED dist/ and nothing else (no OS, no server, no source,
# no .env, no secrets). The deploy hook of the Komodo stack creates (does not start) a container from it, copies the files into
# `releases/<id>/` and switches `current`.
#
# The build stage needs network (the content export of the backend) and a committed pnpm-lock.yaml. Build arguments (Jenkinsfile):
#   SITE_URL, PUBLIC_NOINDEX, CMS_API_URL   public values baked into the build
#   CMS_API_TOKEN                           the export token; it exists only in this build stage, never in the pushed image
FROM node:26-slim AS build

ARG PNPM_VERSION=12.21.0
RUN npm install --global pnpm@${PNPM_VERSION}

WORKDIR /app
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .

ARG SITE_URL
ARG PUBLIC_NOINDEX=false
ARG CMS_API_URL
ARG CMS_API_TOKEN
# A deploy build stands on what the CMS publishes: CMS_SOURCE=api, never the fixture. `pnpm verify` then compares dist/ with the
# export the build was made from and fails the image build on any difference.
ENV CI=true CMS_SOURCE=api
RUN test -n "${CMS_API_URL}" && test -n "${CMS_API_TOKEN}" || (echo "CMS_API_URL and CMS_API_TOKEN build arguments are required" >&2; exit 1)
RUN SITE_URL="${SITE_URL}" PUBLIC_NOINDEX="${PUBLIC_NOINDEX}" CMS_API_URL="${CMS_API_URL}" CMS_API_TOKEN="${CMS_API_TOKEN}" pnpm build \
    && SITE_URL="${SITE_URL}" PUBLIC_NOINDEX="${PUBLIC_NOINDEX}" pnpm verify

FROM scratch
COPY --from=build /app/dist/ /
# `docker create` needs a command; the container is never started.
CMD ["/"]
