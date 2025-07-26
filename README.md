# Birzeit Flat Rent System

A comprehensive web-based apartment rental management system built with PHP and MySQL, designed to connect property owners with potential tenants through a streamlined platform.

 

## Overview

Birzeit Flat Rent is a full-featured apartment rental platform that facilitates the entire rental process from property listing to tenant management. The system supports three distinct user roles with different permissions and functionalities, ensuring a smooth experience for all stakeholders.

## Features

### Property Management
- Complete property listing with detailed specifications
- Photo gallery support for each property
- Marketing information and location details
- Status tracking (pending_approval, approved, rented, rejected, withdrawn)
- Rental conditions and pricing management
- Property reference number generation
- Available date ranges for rentals

### User Management
- Multi-role user system (Customer, Owner, Manager)
- Secure authentication with password validation
- User profiles with contact information and photos
- Session management and security features
- National ID validation for registration
- Bank account information for owners

### Search & Discovery
- Advanced property search with multiple filters
- Sort by price, location, bedrooms, bathrooms
- Availability checking and real-time updates
- Detailed property view with image galleries
- Marketing information display
- Location-based filtering

### Appointment System
- Property preview scheduling with available time slots
- Time slot management for owners
- Booking confirmation system
- Contact information sharing
- Automatic booking status updates

### Rental Process
- Shopping basket for interested properties
- Rental period calculation with start/end dates
- Credit card information collection
- Rental history tracking
- Status management (pending, confirmed, active, completed, cancelled)
- Total amount calculation based on monthly cost

### Communication
- Internal messaging system between users
- Automated system notifications
- Status update alerts
- Message read/unread tracking
- Sender identification system

### Administration
- Manager approval workflow for new listings
- Property verification process
- User role management
- Flat reference number assignment
- Rejection reason tracking

## User Roles

### Property Owner
- Register with bank account details
- Create and manage property listings
- Upload property photos and descriptions
- Set rental terms, pricing, and availability
- Manage preview appointment schedules
- Communicate with potential tenants
- Track property status and rental history
- Receive notifications about bookings and applications

### Customer/Tenant
- Browse available approved properties
- Filter and search based on preferences
- Schedule property viewing appointments
- Add properties to shopping basket
- Apply for rentals with credit card information
- Track rental applications and history
- Receive system notifications and updates

### Manager/Administrator
- Review and approve/reject property listings
- Generate flat reference numbers for approved properties
- Monitor system activity and user accounts
- Handle property verification process
- Send rejection reasons to owners
- Manage overall system operations

## Technology Stack

- **Backend**: PHP 7.4+ with PDO
- **Database**: MySQL 8.0+ with comprehensive schema
- **Frontend**: HTML5, CSS3, JavaScript
- **Architecture**: Modular PHP structure with separation of concerns
- **Security**: PDO Prepared Statements, bcrypt Password Hashing
- **File Management**: Image upload system with validation
- **Session Handling**: PHP sessions with security measures

## Installation

### Prerequisites

- XAMPP/WAMP/LAMP stack or similar
- PHP 7.4 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx)

### Step-by-Step Setup

1. **Clone the Repository**
   ```bash
   git clone https://github.com/yourusername/birzeit-flat-rent.git
   cd birzeit-flat-rent
   ```

2. **Server Setup**
   - Copy the project to your web server directory (e.g., `htdocs` for XAMPP)
   - Ensure PHP and MySQL services are running

3. **Database Configuration**
   - Open `database.inc.php`
   - Update database credentials:
   ```php
   $db_host = 'localhost';
   $db_name = 'birzeit_rent';
   $db_username = 'your_username';
   $db_password = 'your_password';
   ```

4. **Create Required Directories**
   ```bash
   mkdir uploads/flats
   mkdir uploads/profiles
   chmod 755 uploads/
   chmod 755 uploads/flats/
   chmod 755 uploads/profiles/
   ```

## Database Setup

1. **Create Database**
   ```sql
   CREATE DATABASE birzeit_rent;
   USE birzeit_rent;
   ```

2. **Import Schema**
   - Import the provided SQL file: `dbschema_1210225.sql`
   - This includes all tables with comprehensive relationships and sample data

3. **Database Tables**
   - `users` - Base user information for all user types
   - `customers` - Customer-specific data extending users
   - `owners` - Owner-specific data with bank information
   - `managers` - Manager-specific data
   - `flats` - Property listings with detailed specifications
   - `flat_photos` - Image gallery for properties
   - `flat_marketing` - Marketing information and location details
   - `preview_timetables` - Appointment scheduling system
   - `rentals` - Rental agreements and history
   - `messages` - Internal communication system
   - `shopping_basket` - Temporary rental interests
   - `user_sessions` - Multi-step registration support

4. **Default Accounts**
   - Manager: `manager@birzeitflat.com` / `password`
   - Test Owner: `owner@test.com` / `password`
   - Test Customer: `customer@test.com` / `password`
 
## Usage Guide

### For Property Owners

1. **Registration Process**
   - Register with personal information and bank details
   - Await account verification

2. **Property Management**
   - Create detailed property listings with specifications
   - Upload multiple property photos
   - Set rental terms, pricing, and availability periods
   - Wait for manager approval

3. **Tenant Interaction**
   - Manage preview appointment schedules
   - Respond to tenant inquiries through messaging
   - Review rental applications

### For Customers/Tenants

1. **Property Discovery**
   - Browse approved and available properties
   - Use advanced search filters
   - View detailed property information and photo galleries

2. **Application Process**
   - Schedule property viewing appointments
   - Add interesting properties to shopping basket
   - Submit rental applications with dates and payment info

3. **Rental Management**
   - Track application status
   - View rental history and current agreements
   - Communicate with property owners

### For Managers

1. **Property Approval Workflow**
   - Review submitted property listings
   - Verify property information and photos
   - Approve properties and assign reference numbers
   - Reject properties with detailed reasons

2. **System Administration**
   - Monitor user activities
   - Handle system communications
   - Ensure quality control of listings

## Configuration

### Database Functions
The system includes helper functions for database operations:
- `executeQuery()` - Execute prepared statements safely
- `beginTransaction()`, `commitTransaction()`, `rollbackTransaction()` - Transaction management
- ID generation functions for customers, owners, and flat references

### File Upload Settings
Adjust PHP configuration for image uploads:
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20
file_uploads = On
```

### Password Validation Rules
- Length: 6-15 characters
- Must start with a digit
- Must end with a lowercase letter
- Uses bcrypt hashing for storage

## Security Features

- **SQL Injection Protection**: All database queries use PDO prepared statements
- **Password Security**: bcrypt hashing with automatic salt generation
- **Input Validation**: Comprehensive server-side validation
- **File Upload Security**: Type restrictions and size limits
- **Session Management**: Secure session handling with timeout
- **Access Control**: Role-based page access restrictions
- **Data Sanitization**: HTML entity encoding for output
