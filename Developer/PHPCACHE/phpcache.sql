DROP TABLE IF EXISTS phpcache;
CREATE TABLE phpcache (
    SourceURL       VARCHAR(255) NOT NULL PRIMARY KEY,
    Created         TIMESTAMP,
    Headers         TEXT,
    Body            TEXT
) DELAY_KEY_WRITE=1;
