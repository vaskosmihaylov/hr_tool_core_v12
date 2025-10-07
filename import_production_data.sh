#!/bin/bash
# import_production_data.sh - Automated production data import with schema fixes
# Usage: ./import_production_data.sh [path_to_sql_file]

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
DB_USER="vikiyqsb_viki"
DB_PASS="q~V(;w9Nj__z"
DB_NAME="vikiyqsb_viki"
POST_IMPORT_SCRIPT="database/scripts/post_production_import.sql"

# Check if SQL file provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: No SQL file specified${NC}"
    echo "Usage: ./import_production_data.sh [path_to_sql_file]"
    exit 1
fi

SQL_FILE="$1"

# Check if SQL file exists
if [ ! -f "$SQL_FILE" ]; then
    echo -e "${RED}Error: SQL file not found: $SQL_FILE${NC}"
    exit 1
fi

# Check if post-import script exists
if [ ! -f "$POST_IMPORT_SCRIPT" ]; then
    echo -e "${RED}Error: Post-import script not found: $POST_IMPORT_SCRIPT${NC}"
    exit 1
fi

echo -e "${YELLOW}==================================================${NC}"
echo -e "${YELLOW}  Production Database Import Script${NC}"
echo -e "${YELLOW}==================================================${NC}"
echo ""

# Step 1: Create backup
echo -e "${GREEN}Step 1: Creating backup of current database...${NC}"
BACKUP_FILE="backup_before_import_$(date +%Y%m%d_%H%M%S).sql"
mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Backup created: $BACKUP_FILE${NC}"
else
    echo -e "${YELLOW}⚠ Backup failed, continuing anyway...${NC}"
fi
echo ""

# Step 2: Import production dump
echo -e "${GREEN}Step 2: Importing production data from $SQL_FILE...${NC}"
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SQL_FILE" 2>&1 | grep -v "Using a password"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Production data imported successfully${NC}"
else
    echo -e "${RED}✗ Import failed${NC}"
    exit 1
fi
echo ""

# Step 3: Apply post-import schema fixes
echo -e "${GREEN}Step 3: Applying post-import schema fixes...${NC}"
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$POST_IMPORT_SCRIPT" 2>&1 | grep -v "Using a password"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Schema fixes applied successfully${NC}"
else
    echo -e "${RED}✗ Schema fixes failed${NC}"
    exit 1
fi
echo ""

# Step 4: Clear application caches
echo -e "${GREEN}Step 4: Clearing application caches...${NC}"
php artisan cache:clear > /dev/null 2>&1
php artisan config:clear > /dev/null 2>&1
php artisan view:clear > /dev/null 2>&1
echo -e "${GREEN}✓ Caches cleared${NC}"
echo ""

# Step 5: Verify schema
echo -e "${GREEN}Step 5: Verifying schema changes...${NC}"
POSITION_EXISTS=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DESCRIBE viki_workers;" 2>&1 | grep -c "position" || true)
if [ "$POSITION_EXISTS" -gt 0 ]; then
    echo -e "${GREEN}✓ Position column verified in viki_workers table${NC}"
else
    echo -e "${RED}✗ Position column missing in viki_workers table${NC}"
    exit 1
fi
echo ""

# Success summary
echo -e "${YELLOW}==================================================${NC}"
echo -e "${GREEN}✓ Import completed successfully!${NC}"
echo -e "${YELLOW}==================================================${NC}"
echo ""
echo -e "Summary:"
echo -e "  • Backup: $BACKUP_FILE"
echo -e "  • Imported: $SQL_FILE"
echo -e "  • Schema fixes: Applied"
echo -e "  • Caches: Cleared"
echo ""
echo -e "${GREEN}You can now test the application at:${NC}"
echo -e "  http://hr_tool_core_v12.test/service/presences"
echo ""
