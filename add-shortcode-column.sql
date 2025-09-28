-- Add shortcode column to nominees table
-- Run this SQL to add the shortcode field to your nominees table

-- Check if the column already exists before adding it
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'nominees' 
     AND column_name = 'short_code' 
     AND table_schema = DATABASE()) = 0,
    'ALTER TABLE nominees ADD COLUMN short_code VARCHAR(10) UNIQUE AFTER name',
    'SELECT "short_code column already exists" as message'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for better performance
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name = 'nominees' 
     AND index_name = 'idx_short_code' 
     AND table_schema = DATABASE()) = 0,
    'ALTER TABLE nominees ADD INDEX idx_short_code (short_code)',
    'SELECT "idx_short_code index already exists" as message'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Generate shortcodes for existing nominees that don't have them
UPDATE nominees 
SET short_code = CONCAT(
    UPPER(LEFT(REPLACE(REPLACE(REPLACE(name, ' ', ''), '-', ''), '.', ''), 3)),
    LPAD(id, 3, '0')
) 
WHERE short_code IS NULL OR short_code = '';

-- Handle any duplicate shortcodes by adding a suffix
UPDATE nominees n1 
JOIN (
    SELECT short_code, MIN(id) as min_id 
    FROM nominees 
    WHERE short_code IS NOT NULL 
    GROUP BY short_code 
    HAVING COUNT(*) > 1
) n2 ON n1.short_code = n2.short_code AND n1.id != n2.min_id
SET n1.short_code = CONCAT(
    UPPER(LEFT(REPLACE(REPLACE(REPLACE(n1.name, ' ', ''), '-', ''), '.', ''), 2)),
    LPAD(n1.id, 4, '0')
);

-- Show the results
SELECT 'Shortcode column setup completed successfully!' as status;
SELECT COUNT(*) as total_nominees, 
       COUNT(short_code) as nominees_with_shortcodes,
       COUNT(*) - COUNT(short_code) as nominees_without_shortcodes
FROM nominees;
