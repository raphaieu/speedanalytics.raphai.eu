FROM node:24-bookworm

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --omit=dev

RUN npx playwright install --with-deps chromium

COPY . .

CMD ["node", "collector.js"]
