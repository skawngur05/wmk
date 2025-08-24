-- Sample Booklets Table Creation SQL
-- Run this on your live database if you prefer manual table creation

CREATE TABLE sample_booklets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(100) NOT NULL UNIQUE,
    customer_name VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    product_type ENUM('Demo Kit & Sample Booklet', 'Sample Booklet Only', 'Trial Kit', 'Demo Kit Only') NOT NULL,
    tracking_number VARCHAR(100) NULL,
    status ENUM('Pending', 'Shipped', 'Delivered') DEFAULT 'Pending',
    date_ordered DATE NOT NULL,
    date_shipped DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX idx_order_number ON sample_booklets(order_number);
CREATE INDEX idx_status ON sample_booklets(status);
CREATE INDEX idx_date_ordered ON sample_booklets(date_ordered);

-- Optional: If you have existing table with old product types, run these migration queries:
-- UPDATE sample_booklets SET product_type = 'Sample Booklet Only' WHERE product_type = 'Sample Booklet';
-- UPDATE sample_booklets SET product_type = 'Demo Kit Only' WHERE product_type = 'Demo Kit';

-- Then alter the existing table to update ENUM values:
-- ALTER TABLE sample_booklets MODIFY COLUMN product_type ENUM('Demo Kit & Sample Booklet', 'Sample Booklet Only', 'Trial Kit', 'Demo Kit Only') NOT NULL;
