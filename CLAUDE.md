# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP + MySQL web application for generating llms.txt files from XML sitemaps. The application allows users to parse sitemaps, classify URLs, curate content, and generate structured llms.txt files that help AI models understand website structure and content.

## Development Setup Commands

```bash
# Install PHP dependencies
composer install

# Initialize database (MySQL/MariaDB required)
mysql -u root -p < sql/schema.sql

# Start local development server (if using PHP built-in server)
php -S localhost:8000 -t public/

# Or configure Apache/Nginx to serve public/ directory
```

## Testing Commands

```bash
# Run a specific test (when tests are implemented)
vendor/bin/phpunit tests/Services/SitemapParserTest.php

# Check code syntax
php -l src/**/*.php
```

## Architecture

The application follows an MVC-like pattern with clear separation of concerns:

- **Router**: Uses bramus/router for URL routing, configured in `public/index.php`
- **Database Layer**: PDO-based models in `src/Models/` handle all database operations
- **Service Layer**: Business logic lives in `src/Services/`:
  - `SitemapParser`: Parses XML sitemaps (including nested sitemapindex)
  - `UrlClassifier`: Automatically classifies URLs based on patterns
  - `LlmsGenerator`: Generates llms.txt content following the official specification
  - `AiDescriptionService`: Integrates with external AI APIs for auto-generating descriptions
- **Controllers**: Orchestrate models and services, handle HTTP requests/responses
- **Views**: Simple PHP templates with layout inheritance

## Key Implementation Details

### URL Classification Logic
The system automatically classifies URLs into categories (POLICY, CATEGORY, GUIDE, SUPPORT, OTHER) based on URL patterns and keywords. This classification happens in `UrlClassifier::classify()` and can be extended with domain-specific patterns.

### Sitemap Parsing
The parser handles both regular sitemaps and sitemapindex files recursively. It filters out technical URLs (cart, login, pagination) and normalizes lastmod dates. The parsing is resilient to malformed XML and network failures.

### AI Integration
The application can optionally integrate with external AI services for generating short descriptions. This is configured via environment variables (AI_ENABLED, AI_API_BASE_URL, AI_API_KEY). The AI service is called asynchronously from the frontend via AJAX.

### Multi-Project Support
The system supports multiple projects/domains, each with its own set of sitemaps, URLs, sections, and generated llms.txt file. Projects are identified by unique slugs used in public URLs.

## Database Schema

The database uses five main tables:
- `projects`: Site/domain configurations
- `sitemaps`: Sitemap URLs associated with projects
- `urls`: Parsed URLs with classification and metadata
- `sections`: Logical sections for organizing URLs in llms.txt
- `section_url`: Many-to-many relationship for URL organization

## Environment Configuration

Key environment variables in `.env`:
- Database connection (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
- APP_BASE_PATH: Base URL path if not serving from root
- AI service configuration (AI_ENABLED, AI_API_BASE_URL, AI_API_KEY)
- STORAGE_PATH: Directory for generated llms.txt files

## Public Endpoints

The application exposes llms.txt files at `/llms/{project-slug}.txt` for consumption by AI models and crawlers.