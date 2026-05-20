CREATE TABLE IF NOT EXISTS pos_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    table_type ENUM('normal','split') NOT NULL DEFAULT 'normal',
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_pos_tables_status (status),
    INDEX idx_pos_tables_type (table_type)
);

CREATE TABLE IF NOT EXISTS pos_table_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_pos_table_customers_table FOREIGN KEY (table_id) REFERENCES pos_tables(id) ON DELETE CASCADE,
    INDEX idx_pos_table_customers_table (table_id)
);

ALTER TABLE invoice_items
    ADD COLUMN table_customer_id INT NULL,
    ADD CONSTRAINT fk_invoice_items_table_customer FOREIGN KEY (table_customer_id) REFERENCES pos_table_customers(id) ON DELETE SET NULL;
