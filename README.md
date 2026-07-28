## ⚙️ Installation & Local Setup Guide

Follow these steps to run **UniWallet** locally on your machine using XAMPP, WAMP, or MAMP:

### 1. Prerequisites
* Install [XAMPP](https://www.apachefriends.org/index.html) (or any local web server supporting PHP 8+ and MySQL).
* Ensure **Apache** and **MySQL** modules are running in your XAMPP Control Panel.

### 2. Clone the Repository
Open your terminal/command prompt, navigate to your web server's root directory (`C:\xampp\htdocs\`), and clone the repository:
```bash
cd C:\xampp\htdocs\
git clone https://github.com/yourusername/uniwallet.git
```

### 3. Database Setup in phpMyAdmin
1. Open your web browser and navigate to: `http://localhost/phpmyadmin/`
2. Click **New** in the left sidebar to create a new database.
3. Name the database exactly: **`uniWallet`** *(Note the capital 'W')* and click **Create**.
4. With `uniWallet` selected, click on the **Import** tab at the top.
5. Click **Choose File** and select the core database schema located in your cloned folder:
   👉 `C:\xampp\htdocs\uniwallet\sql\schema.sql`
6. Scroll down and click **Import** to generate all 3NF tables and default seed data.
7. *(Optional but Recommended for Full Marks):* Repeat the import process using **`sql/advanced_db.sql`** to load the custom Stored Procedures, Triggers, and SQL Views!