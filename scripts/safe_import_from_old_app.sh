#!/bin/bash

# Safe Import Script from Old Application
# This script ensures MyISAM tables from old dumps don't overwrite InnoDB tables

set -e  # Exit on error

DB_USER="vikiyqsb_viki"
DB_PASS="q~V(;w9Nj__z"
DB_NAME="vikiyqsb_viki"

TABLES="viki_workers viki_work_place viki_regions viki_clients \
viki_special_days viki_vacations viki_work_place_activity \
viki_worker_records viki_approvements viki_archive \
viki_comments viki_supervisor_work_place viki_salary \
viki_manager_region viki_client_region viki_user_region \
viki_work_place_activity_by_month viki_hours_activity_by_month \
viki_work_place_worker viki_workplace_month_budget \
viki_hours_per_day_activity viki_work_place_activity_worker \
viki_worker_bonus viki_workplace_monthly_budget"

echo "=== Safe Import from Old Application ==="
echo ""

# Option 1: Export data only (recommended)
echo "Option 1: Export data only (no table structures)"
echo "Run this on the OLD application server:"
echo ""
echo "mysqldump -u${DB_USER} -p'${DB_PASS}' ${DB_NAME} \\"
echo "  --no-create-info \\"
echo "  --default-character-set=utf8mb4 \\"
echo "  ${TABLES} \\"
echo "  > viki_business_data_SAFE.sql"
echo ""
echo "Then import here with:"
echo "mysql -u${DB_USER} -p'${DB_PASS}' ${DB_NAME} < viki_business_data_SAFE.sql"
echo ""
echo "---"
echo ""

# Option 2: Fix existing dump
echo "Option 2: If you already have a dump with table structures"
echo "Run this to convert MyISAM to InnoDB in the dump file:"
echo ""
echo "sed 's/ENGINE=MyISAM/ENGINE=InnoDB/g' old_dump.sql > new_dump_innodb.sql"
echo "mysql -u${DB_USER} -p'${DB_PASS}' ${DB_NAME} < new_dump_innodb.sql"
echo ""
echo "---"
echo ""

# Option 3: Auto-fix after import
echo "Option 3: If you accidentally imported MyISAM tables again"
echo "Run this to convert them back to InnoDB:"
echo ""
echo "mysql -u${DB_USER} -p'${DB_PASS}' ${DB_NAME} << 'EOSQL'
SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';

ALTER TABLE viki_approvements ENGINE=InnoDB;
ALTER TABLE viki_archive ENGINE=InnoDB;
ALTER TABLE viki_client_region ENGINE=InnoDB;
ALTER TABLE viki_clients ENGINE=InnoDB;
ALTER TABLE viki_comments ENGINE=InnoDB;
ALTER TABLE viki_hours_activity_by_month ENGINE=InnoDB;
ALTER TABLE viki_hours_per_day_activity ENGINE=InnoDB;
ALTER TABLE viki_manager_region ENGINE=InnoDB;
ALTER TABLE viki_regions ENGINE=InnoDB;
ALTER TABLE viki_salary ENGINE=InnoDB;
ALTER TABLE viki_special_days ENGINE=InnoDB;
ALTER TABLE viki_supervisor_work_place ENGINE=InnoDB;
ALTER TABLE viki_user_region ENGINE=InnoDB;
ALTER TABLE viki_vacations ENGINE=InnoDB;
ALTER TABLE viki_work_place ENGINE=InnoDB;
ALTER TABLE viki_work_place_activity ENGINE=InnoDB;
ALTER TABLE viki_work_place_activity_by_month ENGINE=InnoDB;
ALTER TABLE viki_work_place_activity_worker ENGINE=InnoDB;
ALTER TABLE viki_work_place_worker ENGINE=InnoDB;
ALTER TABLE viki_worker_records ENGINE=InnoDB;
ALTER TABLE viki_workers ENGINE=InnoDB;
ALTER TABLE viki_workplace_month_budget ENGINE=InnoDB;
ALTER TABLE viki_worker_bonus ENGINE=InnoDB;
ALTER TABLE viki_workplace_monthly_budget ENGINE=InnoDB;

SELECT 'All tables converted to InnoDB!' AS status;
EOSQL"
echo ""
echo "=== End of Safe Import Instructions ==="
