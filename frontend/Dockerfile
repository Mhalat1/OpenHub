FROM node:19-alpine

WORKDIR /frontend

COPY package*.json ./

RUN npm i
EXPOSE 5174

CMD  ["npm","run", "dev"]