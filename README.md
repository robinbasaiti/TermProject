```md
# Marketplace

A PHP and MySQL second-hand marketplace where users can browse listings, sell items, add products to a cart, place demo orders, message other users, and leave reviews after purchase.

## Overview

Marketplace is a web application built with plain PHP, MySQL, Bootstrap 5, and JavaScript. It focuses on a simple buy-and-sell flow for pre-owned products such as electronics, clothing, books, CDs, vinyl, and collectibles.

## Features

- User registration and login
- Browse and search product listings
- Product detail pages with image gallery
- Sell flow for creating new listings
- Multiple product image uploads
- Shopping cart and checkout flow
- Order history for users
- Buyer and seller messaging
- Review system for verified buyers
- Admin dashboard for products, orders, users, and revenue overview
- CSRF protection on form actions

## Tech Stack

- PHP
- MySQL / MariaDB
- Bootstrap 5
- Bootstrap Icons
- Vanilla JavaScript
- XAMPP for local development

## Project Structure

```text
marketplace/
├── admin/        # Admin dashboard and management pages
├── actions/      # Form handlers such as cart, reviews, and messages
├── css/          # Custom styles
├── db/           # Database connection and SQL dump
├── includes/     # Shared helpers, header/footer, security utilities
├── js/           # Frontend scripts
├── uploads/      # Product images
├── index.php     # Homepage
├── products.php  # Product listing page
├── product.php   # Product details page
├── cart.php      # Shopping cart
├── checkout.php  # Checkout flow
├── sell.php      # Create listing page
├── orders.php    # User orders
├── messages.php  # Messaging page
├── login.php
└── register.php
```

## Installation

1. Clone or download this project into your XAMPP `htdocs` directory.
2. Start Apache and MySQL from the XAMPP control panel.
3. Create a database named `marketplace`.
4. Import the SQL file located at `db/marketplace.sql`.
5. Open `db/conn.php` and update database credentials if your local setup is different.
6. Visit:

```text
http://localhost/marketplace
```

## Default Database Configuration

```php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "marketplace";
```

## Admin Access

The imported SQL dump includes seeded sample users, including an admin account. If you plan to publish, demo, or deploy this project, replace the sample data and update credentials before use.

## Notes

- Checkout is a demo flow and does not process real payments.
- Product images are stored in the `uploads/` directory.
- The application is designed for local development using XAMPP.
- Sample data is included in the SQL dump for quick testing.

## Future Improvements

- Add real payment gateway integration
- Add wishlist and saved items
- Add email notifications
- Improve seller profiles
- Add product moderation tools
- Add pagination and advanced filters
- Move configuration values into environment variables

## Author

Created as a PHP marketplace project for my final submission of Internet Tools Term Project.
```
