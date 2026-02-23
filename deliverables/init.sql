-- Habilita a extensão pgvector para busca semântica
CREATE EXTENSION IF NOT EXISTS vector;

-- Tabela raiz de empresas
CREATE TABLE IF NOT EXISTS companies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Liderança e Conselho
CREATE TABLE IF NOT EXISTS executives (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    fk_company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    expertise JSONB NOT NULL, -- Array de strings (ex: ["Geology", "Finance"])
    technical_summary JSONB NOT NULL, -- Array com 3 bullet points detalhando o histórico
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Ativos/Minas
CREATE TABLE IF NOT EXISTS assets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    fk_company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    commodities JSONB NOT NULL, -- Array de commodities (ex: ["Gold", "Copper"])
    status VARCHAR(100), -- Ex: "operating", "developing"
    country VARCHAR(100),
    state_province VARCHAR(100),
    town VARCHAR(100),
    latitude DECIMAL(10, 8), -- Precisão para mapas
    longitude DECIMAL(11, 8), -- Precisão para mapas
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela da Base de Conhecimento para Vector Store (Ponto Bônus)
CREATE TABLE IF NOT EXISTS knowledge_bases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    fk_company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    raw_content TEXT NOT NULL, -- Markdown bruto raspado
    embedding vector(768), -- Vetor de 768 dimensões para a API do Gemini
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cria um índice HNSW para otimizar as buscas por similaridade semântica
CREATE INDEX IF NOT EXISTS knowledge_bases_embedding_idx 
ON knowledge_bases USING hnsw (embedding vector_cosine_ops);