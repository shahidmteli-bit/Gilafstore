-- Gilaf Store Ecommerce Database Schema
-- Database: ecommerce_db

CREATE DATABASE IF NOT EXISTS ecommerce_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecommerce_db;

-- Users table stores both customers and admins
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  description TEXT,
  category_id INT,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  image VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('pending', 'accepted', 'shipped', 'delivered') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Optional reviews table for product feedback (used in UI placeholders)
CREATE TABLE IF NOT EXISTS product_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Seed default admin user (password: admin123)
INSERT INTO users (name, email, password, is_admin)
VALUES ('Store Admin', 'gilafstore.com', '$2y$10$ugWuOhbtvOY4n0km4G5LpesBlGQzLD0r5XUL37MHdiqzAStFV18Zi', 1)
ON DUPLICATE KEY UPDATE email = email;

-- Sample categories
INSERT INTO categories (name) VALUES
  ('Lifestyle'),
  ('Technology'),
  ('Home Decor'),
  ('Accessories')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Sample products
INSERT INTO products (name, description, category_id, price, stock, image) VALUES
  ('Aurora Ceramic Vase', 'Handcrafted ceramic vase with matte finish for modern interiors.', 3, 64.99, 18, 'sample-vase.jpg'),
  ('Pulse Wireless Headphones', 'Noise-cancelling Bluetooth headphones with 30-hour battery life.', 2, 149.00, 25, 'sample-headphones.jpg'),
  ('Nordic Throw Blanket', 'Soft woven throw with geometric pattern to elevate any living space.', 1, 89.50, 40, 'sample-blanket.jpg'),
  ('Carbon Fiber Backpack', 'Lightweight yet durable backpack designed for urban commuters.', 4, 129.90, 32, 'sample-backpack.jpg')
ON DUPLICATE KEY UPDATE name = VALUES(name);
