-- Add embedding support to polls
ALTER TABLE polls ADD COLUMN allow_embedding BOOLEAN NOT NULL DEFAULT 0;
ALTER TABLE polls ADD COLUMN embed_token VARCHAR(32) NULL;
CREATE INDEX idx_polls_embed_token ON polls(embed_token);
