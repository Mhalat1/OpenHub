FROM node:18 AS build

ENV VITE_API_URL=https://open_hub-backend.onrender.com

WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .

# 👇 Crée .env avec la valeur passée en build-arg
RUN echo "VITE_API_URL=${VITE_API_URL}" > .env

RUN npm run build

FROM nginx:stable-alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]