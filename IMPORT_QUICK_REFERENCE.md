# Production Import Quick Reference Card

## 🚨 Problem Fixed
**Error**: `Column not found: 1054 Unknown column 'position' in 'order clause'`  
**Cause**: Production DB import overwrites local schema enhancements  
**Status**: ✅ RESOLVED

---

## ⚡ Quick Import (One Command)

```bash
./import_production_data.sh viki_business_data.sql
```

This automatically:
- ✓ Backs up current database
- ✓ Imports production data
- ✓ Applies schema fixes
- ✓ Clears caches
- ✓ Verifies everything

---

## 🔧 Manual Import (If Needed)

```bash
# 1. Import data
mysql -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki < viki_business_data.sql

# 2. Fix schema
mysql -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki < database/scripts/post_production_import.sql

# 3. Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```

---

## ✅ Verify It Works

```bash
# Check position column exists
mysql -uvikiyqsb_viki -p'q~V(;w9Nj__z' vikiyqsb_viki -e "DESCRIBE viki_workers;" | grep position

# Expected output:
# position    varchar(191)    YES        NULL
```

Then test: http://hr_tool_core_v12.test/service/presences

---

## 📁 New Files Created

1. **`import_production_data.sh`** - Automated import script ⭐ USE THIS
2. **`database/scripts/post_production_import.sql`** - Schema fix script
3. **`docs/PRODUCTION_IMPORT_GUIDE.md`** - Full documentation

---

## 🛡️ What Was Fixed

### Code Change
**File**: `app/Filament/Service/Resources/PresenceResource/Pages/MonthlyPresence.php`

The code now checks if the column exists before using it:
```php
if (Schema::hasColumn('viki_workers', 'position')) {
    $workersQuery->orderBy('position');
}
```

### Schema Fixes Applied
- ✓ Added `position` column to `viki_workers`
- ✓ Made `note` nullable in `viki_workers`
- ✓ Made `comment` nullable in `viki_vacations`

---

## 🎯 Remember

**After every production import**: Run the post-import script or use the automation script!

---

## 📚 More Info

See: `docs/PRODUCTION_IMPORT_GUIDE.md`
