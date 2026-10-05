FROM node:24.13.1-alpine3.23 AS build

WORKDIR /workspace
COPY package.json package-lock.json ./
COPY apps/frontend/package.json apps/frontend/package.json
COPY packages/api-client/package.json packages/api-client/package.json
RUN npm ci
COPY apps/frontend apps/frontend
COPY packages/api-client packages/api-client
RUN npm run build --workspace @ecommerce/frontend

FROM nginx:1.28.0-alpine3.21

RUN apk upgrade --no-cache
COPY docker/frontend/nginx.conf /etc/nginx/nginx.conf
COPY --from=build /workspace/apps/frontend/dist /usr/share/nginx/html
RUN mkdir -p /tmp/nginx/client_temp /tmp/nginx/proxy_temp /tmp/nginx/fastcgi_temp /tmp/nginx/uwsgi_temp /tmp/nginx/scgi_temp \
    && chown -R nginx:nginx /tmp/nginx /usr/share/nginx/html

USER nginx
EXPOSE 8080
CMD ["nginx", "-g", "daemon off;"]
