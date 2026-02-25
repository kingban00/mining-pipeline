-- Enable the pgvector extension for semantic search
CREATE EXTENSION IF NOT EXISTS vector;

-- Root companies table
CREATE TABLE IF NOT EXISTS companies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) UNIQUE NOT NULL,
    status VARCHAR(50) DEFAULT 'processing', -- processing, completed, rejected (Negative Cache/Lifecycle)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Leadership and Board table
CREATE TABLE IF NOT EXISTS executives (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    fk_company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    expertise JSONB NOT NULL, -- Array of strings (e.g., ["Geology", "Finance"])
    technical_summary JSONB NOT NULL, -- Array with 3 bullet points detailing operational history
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Assets/Mines table
CREATE TABLE IF NOT EXISTS assets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    fk_company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    commodities JSONB NOT NULL, -- Array of commodities (e.g., ["Gold", "Copper"])
    status VARCHAR(100), -- Example: "operating", "developing", "care and maintenance"
    country VARCHAR(100),
    state_province VARCHAR(100),
    town VARCHAR(100),
    latitude DECIMAL(10, 8), -- Map precision
    longitude DECIMAL(11, 8), -- Map precision
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Knowledge Base table for Vector Store (Bonus Point)
CREATE TABLE IF NOT EXISTS knowledge_bases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    fk_company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    raw_content TEXT NOT NULL, -- Raw scraped markdown
    embedding vector(768), -- 768-dimension vector for Gemini API
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create an HNSW index to optimize semantic similarity searches
CREATE INDEX IF NOT EXISTS knowledge_bases_embedding_idx 
ON knowledge_bases USING hnsw (embedding vector_cosine_ops);