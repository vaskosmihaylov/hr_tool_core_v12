# Production Database Import Guide

## Overview
This guide provides the proper procedure for importing production database dumps into the development environment without breaking functionality.

## Problem
When importing production SQL dumps directly, table structures from production overwrite local schema changes that were applied via Laravel migrations. This causes errors like:
- `Column not found: 1054 Unknown column 'position' in 'order clause'`
- Missing nullable columns
- Other schema mismatches

## Root Cause
The development environment has schema enhancements (via migrations) that don't exist in production yet. When the production dump is imported:
1. It contains `CREATE TABLE` or `ALTER TABLE` statements that define the production schema
2. These statements overwrite any local schema changes
3. The `migrations` table shows migrations as "run", but the actual columns are missing

## Affected Migrations
The following migrations modify production tables and will be lost during import:

1. **2025_08_28_122118_make_note_nullable_in_viki_workers_table**
   - Makes `note` field nullable in `viki_workers`
   - Converts empty strings to NULL

2. **2025_08_28_124928_add_position_field_to_viki_workers_table**
   - Adds `position` VARCHAR(191) NULL column to `viki_workers`
   - Used for custom worker ordering in presence views

3. **2025_08_28_131258_make_vacation_comment_nullable_in_viki_vacations_table**
   - Makes `comment` field nullable in `viki_vacations`

## Recommended Import Procedure

### Method 1: Using the Post-Import Script (Recommended)

```bash
# 1. Create database backup (optional but recommended)
mysqldump -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki > backup_before_import_$(date +%Y%m%d_%H%M%S).sql

# 2. Import production dump
mysql -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki < viki_business_data.sql

# 3. Apply post-import schema fixes
mysql -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki < database/scripts/post_production_import.sql

# 4. Clear application cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# 5. Verify the application works
php artisan tinker
# In tinker: Schema::hasColumn('viki_workers', 'position')
# Should return: true
```

### Method 2: Manual Migration Approach

```bash
# 1. Import production dump
mysql -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki < viki_business_data.sql

# 2. Reset specific migration records (to allow re-running)
php artisan db:seed --class=ResetProductionMigrations  # If seeder exists

# OR manually via SQL:
mysql -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki -e "
    DELETE FROM migrations WHERE migration IN (
        '2025_08_28_122118_make_note_nullable_in_viki_workers_table',
        '2025_08_28_124928_add_position_field_to_viki_workers_table',
        '2025_08_28_131258_make_vacation_comment_nullable_in_viki_vacations_table'
    );
"

# 3. Re-run the migrations
php artisan migrate --path=database/migrations/2025_08_28_122118_make_note_nullable_in_viki_workers_table.php
php artisan migrate --path=database/migrations/2025_08_28_124928_add_position_field_to_viki_workers_table.php
php artisan migrate --path=database/migrations/2025_08_28_131258_make_vacation_comment_nullable_in_viki_vacations_table.php

# 4. Clear caches
php artisan cache:clear
php artisan config:clear
```

## Verification Steps

After import, verify everything is working:

```bash
# Check that position column exists
mysql -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki -e "DESCRIBE viki_workers;" | grep position

# Check application
php artisan tinker
>>> Schema::hasColumn('viki_workers', 'position')
=> true

# Test the presence functionality
# Navigate to: http://hr_tool_core_v12.test/service/presences
# Try to configure a month - should work without errors
```

## Code-Level Protection

The code has been updated to handle missing columns gracefully:

**File**: `app/Filament/Service/Resources/PresenceResource/Pages/MonthlyPresence.php`

```php
// Order by position if column exists (for development/future compatibility)
if (Schema::hasColumn('viki_workers', 'position')) {
    $workersQuery->orderBy('position');
}
```

This ensures the application won't crash even if the column is missing temporarily.

## Future-Proofing

### For New Migrations on Production Tables

When creating new migrations that modify production tables (`viki_*` prefix):

1. **Document the migration** in this file
2. **Update** `database/scripts/post_production_import.sql` to include the new schema change
3. **Add idempotency checks** to ensure the script can be run multiple times safely
4. **Consider adding Schema checks** in the code if the column is critical

### For Production Deployment

When deploying to production, these migrations should be applied:

```bash
# On production server
php artisan migrate --force
```

This will ensure production has all schema enhancements.

## Common Issues and Solutions

### Issue: "Column not found" errors after import
**Solution**: Run the post-import script

### Issue: Migration already marked as run
**Solution**: Use Method 1 (post-import script) instead of Method 2

### Issue: Date errors (0000-00-00) during ALTER
**Solution**: The post-import script handles this by disabling strict mode temporarily

### Issue: Application still shows errors
**Solution**: Clear all caches:
```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

## Production Dump Command Reference

### Current Production Dump Command
```bash
mysqldump -uvikiyqsb_viki -p'pass' vikiyqsb_viki \
  viki_workers viki_work_place viki_regions viki_clients \
  viki_special_days viki_vacations viki_work_place_activity \
  viki_worker_records viki_approvements viki_archive \
  viki_comments viki_supervisor_work_place viki_salary \
  viki_manager_region viki_client_region viki_user_region \
  viki_work_place_activity_by_month viki_hours_activity_by_month \
  viki_work_place_worker viki_workplace_month_budget \
  viki_hours_per_day_activity viki_work_place_activity_worker \
  viki_worker_bonus viki_workplace_monthly_budget \
  --default-character-set=utf8mb4 \
  > viki_business_data.sql
```

### Recommended: Add Skip Extended Insert for Debugging
```bash
mysqldump -uvikiyqsb_viki -p'pass' vikiyqsb_viki \
  --skip-extended-insert \
  --default-character-set=utf8mb4 \
  [table names...] \
  > viki_business_data.sql
```

## Notes

- **Always backup** before importing production data
- The `migrations` table is **not included** in the production dump, which is correct
- Local migrations table remains intact after import
- Schema changes must be reapplied after each production import
- Consider automating this process with a bash script

## Automation Script (Future Enhancement)

Consider creating `import_production_data.sh`:

```bash
#!/bin/bash
# import_production_data.sh - Automated production data import

echo "Importing production database..."
mysql -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki < viki_business_data.sql

echo "Applying post-import schema fixes..."
mysql -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki < database/scripts/post_production_import.sql

echo "Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan view:clear

echo "Verifying schema..."
php artisan tinker --execute="var_dump(Schema::hasColumn('viki_workers', 'position'));"

echo "Import complete!"
```

## Contact

If you encounter issues not covered in this guide, check the project's memory files or consult the development team.
