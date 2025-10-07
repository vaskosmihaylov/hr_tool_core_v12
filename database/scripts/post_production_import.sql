-- Post-Production Import Schema Fixes
-- Run this script after importing production database to ensure schema compatibility
-- Usage: mysql -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki < database/scripts/post_production_import.sql

-- Disable strict mode to handle legacy data issues
SET sql_mode = '';

-- 1. Make note field nullable in viki_workers table
-- Migration: 2025_08_28_122118_make_note_nullable_in_viki_workers_table
ALTER TABLE viki_workers MODIFY COLUMN note VARCHAR(191) NULL;
UPDATE viki_workers SET note = NULL WHERE note = '';

-- 2. Add position field to viki_workers table
-- Migration: 2025_08_28_124928_add_position_field_to_viki_workers_table
-- Check if column exists first to make this script idempotent
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'viki_workers' 
    AND COLUMN_NAME = 'position'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE viki_workers ADD COLUMN position VARCHAR(191) NULL AFTER note',
    'SELECT "Column position already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Make comment field nullable in viki_vacations table
-- Migration: 2025_08_28_131258_make_vacation_comment_nullable_in_viki_vacations_table
ALTER TABLE viki_vacations MODIFY COLUMN comment TEXT NULL;

-- Re-enable strict mode
SET sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

SELECT 'Post-import schema fixes applied successfully!' AS result;
