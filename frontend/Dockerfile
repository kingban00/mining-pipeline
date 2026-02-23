FROM node:20-alpine

# Define o diretório de trabalho
WORKDIR /app

# Instala o Angular CLI globalmente
RUN npm install -g @angular/cli

# Copia os arquivos de dependência
COPY package*.json ./

# Instala as dependências
RUN npm install

# O código fonte será mapeado via volume no docker-compose
EXPOSE 4200