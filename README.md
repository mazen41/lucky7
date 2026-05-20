# MAI Salon Website

A beautiful, modern Arabic beauty salon website built with PHP, TailwindCSS, and MySQL. Features a complete content management system for easy administration.

## 🌟 Features

### Frontend
- **Responsive Design**: Mobile-first approach with TailwindCSS
- **Arabic RTL Support**: Full right-to-left language support
- **Modern UI**: Beautiful design matching the original MAI Salon branding
- **Interactive Booking System**: Online appointment booking with form validation
- **Service Showcase**: Dynamic service listings with pricing
- **Team Profiles**: Staff member profiles with experience details
- **Customer Reviews**: Display approved customer testimonials
- **Working Hours**: Dynamic business hours display
- **Contact Information**: Easy-to-find contact details and social media links

### Admin Panel
- **Secure Authentication**: Admin login system with session management
- **Content Management**: Edit all website text content dynamically
- **Service Management**: Add, edit, and manage salon services
- **Team Management**: Manage staff profiles and information
- **Booking Management**: View and manage customer appointments
- **Review Management**: Approve and manage customer reviews
- **Settings Management**: Update contact info and social media links
- **Dashboard Analytics**: Overview of bookings, services, and team stats

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, TailwindCSS, JavaScript
- **Icons**: Font Awesome 6
- **Fonts**: Google Fonts (Tajawal for Arabic)

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## 🚀 Installation

### 1. Database Setup
```sql
-- Import the database schema
mysql -u your_username -p < database.sql
```

### 2. Database Configuration
Edit `config/database.php` and update your database credentials:
```php
private $host = 'localhost';
private $db_name = 'mai_salon';
private $username = 'your_username';
private $password = 'your_password';
```

### 3. File Permissions
Ensure the web server has read/write permissions to the project directory.

### 4. Web Server Setup
Point your web server document root to the project directory.

## 🔐 Admin Access

### Default Admin Credentials
- **Username**: `admin`
- **Password**: `admin123`

**⚠️ Important**: Change the default admin password after first login for security.

### Admin Panel Access
Visit: `your-domain.com/admin/login.php`

## 📁 Project Structure

```
mai-salon/
├── admin/                  # Admin panel files
│   ├── auth.php           # Authentication helper
│   ├── content.php        # Content management
│   ├── dashboard.php      # Admin dashboard
│   ├── login.php          # Admin login
│   └── logout.php         # Admin logout
├── assets/                # Static assets
│   └── images/            # Website images
├── config/                # Configuration files
│   └── database.php       # Database configuration
├── booking.php            # Booking form handler
├── database.sql           # Database schema
├── index.php              # Main homepage
└── README.md              # This file
```

## 🎨 Customization

### Colors
The website uses a warm color palette:
- **Primary Brown**: `#8B4513`
- **Gold Accent**: `#D4AF37`
- **Cream Background**: `#F5E6D3`
- **Dark Brown**: `#654321`

### Content Management
All text content can be edited through the admin panel:
1. Login to admin panel
2. Navigate to "إدارة المحتوى" (Content Management)
3. Edit any text field and save

### Adding Services
1. Access admin panel
2. Go to "الخدمات" (Services)
3. Add new service with title, description, price, and duration

### Managing Team Members
1. Access admin panel
2. Go to "الفريق" (Team)
3. Add team member profiles with photos and experience details

## 📱 Responsive Design

The website is fully responsive and optimized for:
- Desktop (1024px+)
- Tablet (768px - 1023px)
- Mobile (320px - 767px)

## 🔒 Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention with prepared statements
- Session-based authentication
- CSRF protection on forms
- Input validation and sanitization

## 🌐 Browser Support

- Chrome 60+
- Firefox 60+
- Safari 12+
- Edge 79+

## 📞 Support

For technical support or customization requests, please contact the development team.

## 📄 License

This project is proprietary software developed for MAI Salon. All rights reserved.

## 🚀 Deployment

### Production Checklist
- [ ] Change default admin password
- [ ] Update database credentials
- [ ] Configure SSL certificate
- [ ] Set up regular database backups
- [ ] Configure error logging
- [ ] Optimize images for web
- [ ] Test all functionality
- [ ] Set up monitoring

### Performance Optimization
- Enable gzip compression
- Optimize images (WebP format recommended)
- Use CDN for static assets
- Enable browser caching
- Minify CSS/JS files

## 🔄 Updates

To update the website:
1. Backup current files and database
2. Upload new files
3. Run any database migrations
4. Test functionality
5. Clear any caches

---

**Built with ❤️ for MAI Salon**
