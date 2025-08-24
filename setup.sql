-- Lead Management System Database Setup (MySQL)

-- Create database
CREATE DATABASE IF NOT EXISTS wrap_my_kitchen_crm;
USE wrap_my_kitchen_crm;

-- Users table for authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default users (only if they don't exist)
INSERT IGNORE INTO users (username, password, full_name) VALUES
('kim', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kim'),
('patrick', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Patrick'),
('lina', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lina');

-- Leads table
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_created DATE NOT NULL,
    lead_origin ENUM('Facebook', 'Google Text', 'Instagram', 'Trade Show', 'WhatsApp', 'Commercial', 'Referral') NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    next_followup_date DATE,
    remarks ENUM('Not Interested', 'Not Service Area', 'Not Compatible', 'Sold', 'In Progress', 'New') DEFAULT 'New',
    assigned_to ENUM('Kim', 'Patrick', 'Lina') NOT NULL,
    assigned_installer VARCHAR(100) NULL COMMENT 'Installer assigned to this installation (Angel, Brian, Luis, etc.)',
    notes TEXT,
    additional_notes TEXT,
    project_amount DECIMAL(10,2) DEFAULT 0.00,
    deposit_paid TINYINT(1) DEFAULT 0 COMMENT 'Whether deposit has been paid',
    balance_paid TINYINT(1) DEFAULT 0 COMMENT 'Whether balance has been paid',
    installation_date DATE NULL COMMENT 'Scheduled installation date',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create indexes
CREATE INDEX idx_next_followup ON leads(next_followup_date);
CREATE INDEX idx_date_created ON leads(date_created);
CREATE INDEX idx_lead_origin ON leads(lead_origin);
CREATE INDEX idx_assigned_to ON leads(assigned_to);
CREATE INDEX idx_assigned_installer ON leads(assigned_installer);
CREATE INDEX idx_remarks ON leads(remarks);
CREATE INDEX idx_installation_date ON leads(installation_date);

-- Installers table
CREATE TABLE IF NOT EXISTS installers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    hire_date DATE NULL,
    hourly_rate DECIMAL(10,2) NULL,
    specialty TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default installers (only if they don't exist)
INSERT IGNORE INTO installers (name, status, created_at) VALUES
('Angel', 'active', NOW()),
('Brian', 'active', NOW()),
('Luis', 'active', NOW());

-- Create indexes for installers table
CREATE INDEX idx_installer_name ON installers(name);
CREATE INDEX idx_installer_status ON installers(status);