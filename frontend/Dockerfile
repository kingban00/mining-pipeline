FROM node:20-alpine

# Set working directory
WORKDIR /app

# Install Angular CLI globally
RUN npm install -g @angular/cli

# Copy dependency files
COPY package*.json ./

# Install dependencies
RUN npm install

# The source code will be mapped via volume in docker-compose
EXPOSE 4200