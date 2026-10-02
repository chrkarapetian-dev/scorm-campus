FROM node:22-alpine
WORKDIR /app
COPY package.json server.js app.js course-data.js index.html styles.css ./
ENV NODE_ENV=production HOST=0.0.0.0 PORT=3000 DATA_DIR=/data
RUN mkdir -p /data && chown node:node /data
USER node
EXPOSE 3000
CMD ["node", "server.js"]
