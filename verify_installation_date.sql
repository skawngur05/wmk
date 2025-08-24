-- SQL script to verify installation_date column exists
-- Run this to manually add the column if needed

-- Check if installation_date column exists
SHOW COLUMNS FROM leads LIKE 'installation_date';

-- If the column doesn't exist, run this command:
-- ALTER TABLE leads ADD COLUMN installation_date DATE NULL;

-- Sample query to verify data structure
SELECT id, name, remarks, deposit_paid, balance_paid, installation_date, project_amount 
FROM leads 
WHERE remarks = 'Sold' 
LIMIT 5;

-- Check current database structure
DESCRIBE leads;
