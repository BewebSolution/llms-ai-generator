-- LLMS.txt Generator Database Schema
-- Version 2.0 with Web Crawling Support

-- Database creation
CREATE DATABASE IF NOT EXISTS llms_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE llms_app;

-- Projects table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    site_summary TEXT,
    description TEXT,
    -- New crawling fields
    homepage_url VARCHAR(500),
    crawl_depth INT DEFAULT 3,
    crawl_status ENUM('pending', 'in_progress', 'completed', 'failed', 'stopped') DEFAULT 'pending',
    max_urls INT DEFAULT 500,
    last_crawl_at TIMESTAMP NULL,
    crawl_error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_crawl_status (crawl_status)
) ENGINE=InnoDB;

-- Sitemaps table
CREATE TABLE IF NOT EXISTS sitemaps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    last_parsed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB;

-- URLs table
CREATE TABLE IF NOT EXISTS urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    loc VARCHAR(500) NOT NULL,
    lastmod DATE NULL,
    type ENUM('HOMEPAGE', 'CATEGORY', 'PRODUCT', 'GUIDE', 'BLOG', 'SUPPORT', 'POLICY', 'OTHER') DEFAULT 'OTHER',
    title VARCHAR(255),
    short_description TEXT,
    is_selected TINYINT(1) DEFAULT 1,
    -- New fields for crawled content
    content_hash VARCHAR(64),
    crawl_depth INT DEFAULT 0,
    http_status INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_loc (project_id, loc),
    INDEX idx_project (project_id),
    INDEX idx_type (type),
    INDEX idx_selected (is_selected)
) ENGINE=InnoDB;

-- Sections table
CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50),
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB;

-- Section URLs junction table
CREATE TABLE IF NOT EXISTS section_url (
    section_id INT NOT NULL,
    url_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    PRIMARY KEY (section_id, url_id),
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'boolean', 'integer', 'float', 'json') DEFAULT 'string',
    description VARCHAR(500),
    category VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_category (category)
) ENGINE=InnoDB;

-- AI Usage Log table
CREATE TABLE IF NOT EXISTS ai_usage_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    url_id INT,
    operation_type VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    input_tokens INT DEFAULT 0,
    output_tokens INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    estimated_cost DECIMAL(10, 6) DEFAULT 0,
    success TINYINT(1) DEFAULT 1,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE SET NULL,
    INDEX idx_project (project_id),
    INDEX idx_created (created_at),
    INDEX idx_operation (operation_type)
) ENGINE=InnoDB;

-- Crawl Queue table (for tracking URLs to crawl)
CREATE TABLE IF NOT EXISTS crawl_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    depth INT DEFAULT 0,
    status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') DEFAULT 'pending',
    error_message TEXT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_status (project_id, status),
    INDEX idx_status (status),
    UNIQUE KEY unique_project_url (project_id, url)
) ENGINE=InnoDB;

-- Crawl Stats table (for tracking crawl progress)
CREATE TABLE IF NOT EXISTS crawl_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL UNIQUE,
    total_discovered INT DEFAULT 0,
    total_crawled INT DEFAULT 0,
    total_failed INT DEFAULT 0,
    total_skipped INT DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;
