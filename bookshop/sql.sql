-- ===============================================
-- BOOKSHOP MANAGEMENT SYSTEM DATABASE (SIMPLE VERSION)
-- ===============================================

-- Create database
CREATE DATABASE IF NOT EXISTS bookshop 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Use the database
USE bookshop;

-- =============================================
-- ADMIN TABLE (NEW - SIMPLE VERSION)
-- =============================================

-- Create admin table for simple login
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- ================================================
-- MAIN TABLES (EXISTING)
-- ================================================

-- Create customers table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP NULL
);

-- Create books table
CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP NULL
);

-- Create orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    book_id INT NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

-- Create cart table
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    book_id INT NOT NULL,
    quantity INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    UNIQUE KEY unique_customer_book (customer_id, book_id)
);

-- ================================================
-- DELETION TRACKING TABLES (EXISTING)
-- ================================================

-- Table to store deleted customers information
CREATE TABLE deleted_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_customer_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    registration_date TIMESTAMP NOT NULL,
    deletion_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_by_admin BOOLEAN DEFAULT TRUE,
    deletion_reason TEXT,
    INDEX idx_original_customer_id (original_customer_id)
);

-- Table to store deleted books information
CREATE TABLE deleted_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_book_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock_at_deletion INT NOT NULL,
    creation_date TIMESTAMP NOT NULL,
    deletion_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_by_admin BOOLEAN DEFAULT TRUE,
    deletion_reason TEXT,
    INDEX idx_original_book_id (original_book_id)
);

-- Table to store deleted orders information
CREATE TABLE deleted_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_order_id INT NOT NULL,
    customer_id INT NOT NULL,
    customer_name VARCHAR(100),
    customer_email VARCHAR(100),
    book_id INT NOT NULL,
    book_title VARCHAR(200),
    book_author VARCHAR(100),
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    order_date TIMESTAMP NOT NULL,
    deletion_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_by_admin BOOLEAN DEFAULT TRUE,
    deletion_reason TEXT,
    INDEX idx_original_order_id (original_order_id),
    INDEX idx_original_customer_id (customer_id),
    INDEX idx_original_book_id (book_id)
);

-- ================================================
-- TRIGGERS FOR AUTOMATIC DELETION TRACKING
-- ================================================

-- Trigger for customers deletion
DELIMITER $$
CREATE TRIGGER tr_customers_before_delete
    BEFORE DELETE ON customers
    FOR EACH ROW
BEGIN
    -- Store deleted customer information
    INSERT INTO deleted_customers (
        original_customer_id, name, email, phone, 
        registration_date, deletion_reason
    ) VALUES (
        OLD.id, OLD.name, OLD.email, OLD.phone, 
        OLD.created_at, 'Admin deleted customer'
    );
    
    -- Also store any related orders before customer deletion
    INSERT INTO deleted_orders (
        original_order_id, customer_id, customer_name, customer_email,
        book_id, book_title, book_author, quantity, total_price, 
        status, order_date, deletion_reason
    )
    SELECT 
        o.id, o.customer_id, OLD.name, OLD.email,
        o.book_id, b.title, b.author, o.quantity, o.total_price,
        o.status, o.order_date, 'Customer deleted - preserving order history'
    FROM orders o
    LEFT JOIN books b ON o.book_id = b.id
    WHERE o.customer_id = OLD.id;
END$$

-- Trigger for books deletion
CREATE TRIGGER tr_books_before_delete
    BEFORE DELETE ON books
    FOR EACH ROW
BEGIN
    -- Store deleted book information
    INSERT INTO deleted_books (
        original_book_id, title, author, price, stock_at_deletion,
        creation_date, deletion_reason
    ) VALUES (
        OLD.id, OLD.title, OLD.author, OLD.price, OLD.stock,
        OLD.created_at, 'Admin deleted book'
    );
    
    -- Also store any related orders before book deletion
    INSERT INTO deleted_orders (
        original_order_id, customer_id, customer_name, customer_email,
        book_id, book_title, book_author, quantity, total_price, 
        status, order_date, deletion_reason
    )
    SELECT 
        o.id, o.customer_id, c.name, c.email,
        o.book_id, OLD.title, OLD.author, o.quantity, o.total_price,
        o.status, o.order_date, 'Book deleted - preserving order history'
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.book_id = OLD.id;
END$$

-- Trigger for orders deletion (direct deletion)
CREATE TRIGGER tr_orders_before_delete
    BEFORE DELETE ON orders
    FOR EACH ROW
BEGIN
    DECLARE customer_name VARCHAR(100) DEFAULT 'Unknown';
    DECLARE customer_email VARCHAR(100) DEFAULT 'Unknown';
    DECLARE book_title VARCHAR(200) DEFAULT 'Unknown';
    DECLARE book_author VARCHAR(100) DEFAULT 'Unknown';
    
    -- Get customer info if exists
    SELECT name, email INTO customer_name, customer_email
    FROM customers WHERE id = OLD.customer_id LIMIT 1;
    
    -- Get book info if exists
    SELECT title, author INTO book_title, book_author
    FROM books WHERE id = OLD.book_id LIMIT 1;
    
    -- Store deleted order information
    INSERT INTO deleted_orders (
        original_order_id, customer_id, customer_name, customer_email,
        book_id, book_title, book_author, quantity, total_price, 
        status, order_date, deletion_reason
    ) VALUES (
        OLD.id, OLD.customer_id, customer_name, customer_email,
        OLD.book_id, book_title, book_author, OLD.quantity, OLD.total_price,
        OLD.status, OLD.order_date, 'Order directly deleted by admin'
    );
END$$

DELIMITER ;

-- ================================================
-- INSERT SAMPLE DATA
-- ================================================

-- Insert admin credentials (PLAIN TEXT - NO HASHING)
INSERT INTO admins (email, password, name) VALUES 
('admin@gmail.com', 'admin@123', 'Admin User');

-- Insert sample books
INSERT INTO books (title, author, price, stock) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', 500, 50),
('To Kill a Mockingbird', 'Harper Lee', 450, 30),
('1984', 'George Orwell', 300, 40),
('Pride and Prejudice', 'Jane Austen', 250, 25),
('Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling', 600, 60),
('The Catcher in the Rye', 'J.D. Salinger', 650, 35),
('Lord of the Flies', 'William Golding', 280, 28),
('The Hobbit', 'J.R.R. Tolkien', 810, 45),
('Fahrenheit 451', 'Ray Bradbury', 200, 33),
('Jane Eyre', 'Charlotte Brontë', 120, 22),
('Brave New World', 'Aldous Huxley', 220, 38),
('The Lord of the Rings', 'J.R.R. Tolkien', 300, 42),
('Animal Farm', 'George Orwell', 410, 55),
('Of Mice and Men', 'John Steinbeck', 340, 31),
('The Kite Runner', 'Khaled Hosseini', 670, 27),
('The Alchemist', 'Paulo Coelho', 120, 44),
('One Hundred Years of Solitude', 'Gabriel García Márquez', 820, 19),
('The Picture of Dorian Gray', 'Oscar Wilde',120, 36),
('Wuthering Heights', 'Emily Brontë', 440, 29),
('The Count of Monte Cristo', 'Alexandre Dumas', 450, 21);

-- Insert sample customer for testing
-- Email: test@example.com, Password: test123! (plain text for this example)
INSERT INTO customers (name, email, phone, password) VALUES
('Test User', 'test@example.com', '9876543210', 'test@1');

-- Insert a sample order for testing
INSERT INTO orders (customer_id, book_id, quantity, total_price, status) VALUES
(1, 1, 2, 1000, 'confirmed'),
(1, 3, 1, 600, 'pending');

-- ================================================
-- VIEWS FOR EASIER DATA ACCESS
-- ================================================

-- View for complete order history (active + deleted)
CREATE VIEW v_complete_order_history AS
SELECT 
    o.id, o.customer_id, c.name as customer_name, c.email as customer_email,
    o.book_id, b.title as book_title, b.author as book_author, b.price as book_price,
    o.quantity, o.total_price, o.status, o.order_date,
    'ACTIVE' as record_status, NULL as deletion_date
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.id
LEFT JOIN books b ON o.book_id = b.id

UNION ALL

SELECT 
    do.original_order_id as id, do.customer_id, do.customer_name, do.customer_email,
    do.book_id, do.book_title, do.book_author, NULL as book_price,
    do.quantity, do.total_price, do.status, do.order_date,
    'DELETED' as record_status, do.deletion_date
FROM deleted_orders do
ORDER BY order_date DESC;

-- ================================================
-- VERIFICATION QUERIES
-- ================================================

-- Show admin credentials
SELECT 'Admin Login Details:' as Info;
SELECT email, password, name FROM admins;

-- Show all tables
SHOW TABLES;

-- Count records in each table
SELECT 'admins' as table_name, COUNT(*) as record_count FROM admins
UNION ALL
SELECT 'customers' as table_name, COUNT(*) as record_count FROM customers
UNION ALL
SELECT 'books' as table_name, COUNT(*) as record_count FROM books
UNION ALL
SELECT 'orders' as table_name, COUNT(*) as record_count FROM orders
UNION ALL
SELECT 'cart' as table_name, COUNT(*) as record_count FROM cart
UNION ALL
SELECT 'deleted_customers' as table_name, COUNT(*) as record_count FROM deleted_customers
UNION ALL
SELECT 'deleted_books' as table_name, COUNT(*) as record_count FROM deleted_books
UNION ALL

SELECT 'deleted_orders' as table_name, COUNT(*) as record_count FROM deleted_orders;



