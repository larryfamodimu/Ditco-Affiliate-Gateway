FROM node:18-alpine

WORKDIR /app

# Copy package.json (no npm install needed — zero dependencies)
COPY package.json ./
COPY server.js    ./

EXPOSE 3000

CMD ["node", "server.js"]
