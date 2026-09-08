# 🔧 EFIX Repair Booking Form Plugin

A modern, professional WordPress plugin for mobile device repair booking services. Features a multi-step form, smart cart system, admin management, and comprehensive booking system.

## ✨ **Latest Updates & Features**

### **🎨 Modern Admin Interface (Latest)**
- **Dashboard Page**: Professional design with status cards, performance metrics, and brand rankings
- **Payment Settings**: Modern styling with gradient backgrounds and professional layout
- **Bookings Management**: Enhanced table with Print Invoice functionality and improved styling
- **Icon Management**: Consistent 30px sizing for all icons and emojis across admin pages

### **📱 Enhanced Frontend Features**
- **Multi-step Form**: 4-step booking process with progress tracking
- **Smart Cart System**: Add/remove repair services with real-time total calculation
- **International Phone Validation**: Supports all country phone numbers (not just UAE)
- **Professional Radio Buttons**: Perfectly round, custom-styled radio buttons
- **Responsive Design**: Mobile-friendly interface with modern UI elements

### **🧾 Invoice & Print System**
- **Print Invoice Button**: Available on both frontend success page and admin bookings
- **Professional Invoice Design**: Company branding, customer details, service breakdown
- **Short Booking IDs**: Professional format `EFIX-123456-ABC` (15 characters)
- **Calendar Integration**: Add bookings to Google Calendar, Outlook, or download iCal

### **🏪 Store & Delivery Settings**
- **Admin Configuration**: New section in Payment Settings page for store information
- **Dynamic Frontend**: Store address, contact details, and working hours automatically update on the booking form
- **Shortcode Support**: Use `[rbf_store_info]` to display store information anywhere on your site
- **Configurable Fields**: Address, city, emirate, phone, WhatsApp, email, and working hours

## 🚀 **Core Features**

### **Device Selection System**
- **Brand Selection**: Visual brand cards with icons
- **Model Selection**: Dynamic model loading based on selected brand
- **Custom Device Support**: "Others" option for custom brand/model input
- **Smart Navigation**: Automatic progression between steps

### **Repair Services Management**
- **Service Catalog**: Comprehensive repair services with pricing
- **Smart Cart**: Add multiple services with quantity management
- **Real-time Pricing**: Subtotal, VAT (5%), and total calculation
- **Service Options**: Pickup, Delivery, Onsite, Store Visit

### **Admin Management System**
- **Dashboard Overview**: Performance metrics, recent bookings, top brands
- **Booking Management**: View, edit, delete, and print invoices
- **Payment Settings**: Configure payment gateways and settings
- **Data Export**: Comprehensive booking data management

## 📋 **Installation & Setup**

### **Requirements**
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- jQuery (usually included with WordPress)

### **Installation Steps**
1. Upload plugin files to `/wp-content/plugins/repair-booking-form/`
2. Activate the plugin through WordPress admin
3. Configure plugin settings in the admin panel
4. Add the shortcode `[repair_booking_form]` to any page/post

### **Database Setup**
The plugin automatically creates the required database table:
```sql
wp_rbf_bookings
```

## 🎯 **Usage**

### **Frontend Shortcodes**
```php
[repair_booking_form]
[rbf_store_info]
[rbf_brands_models]
```

### **Store Information Shortcode**
   ```php
[rbf_store_info show_address="true" show_contact="true" show_hours="true"]
```
**Attributes:**
- `show_address`: Display store address (default: true)
- `show_contact`: Display contact information (default: true)  
- `show_hours`: Display working hours (default: true)
- `class`: Custom CSS class (default: rbf-store-info)

### **Multi-step Process**
1. **Step 1**: Select device brand
2. **Step 2**: Choose device model
3. **Step 3**: Select repair services and add to cart
4. **Step 4**: Customer information and service details

### **Admin Access**
- **Dashboard**: `/wp-admin/admin.php?page=rbf-dashboard`
- **Bookings**: `/wp-admin/admin.php?page=rbf-bookings`
- **Payment Settings**: `/wp-admin/admin.php?page=rbf-payment-settings`
  - Payment gateway configuration
  - Store & delivery address settings

## 🔧 **Technical Details**

### **File Structure**
```
repair-booking-form/
├── repair-booking-form.php          # Main plugin file
├── assets/
│   ├── css/
│   │   ├── admin.css               # Admin panel styling
│   │   └── style.css               # Frontend form styling
│   ├── js/
│   │   └── main.js                 # Frontend JavaScript
│   └── images/                     # Plugin images and icons
├── templates/
│   └── form.php                    # Frontend form template
├── brands_models_data.json         # Device data
└── README.md                       # This documentation
```

### **AJAX Endpoints**
- `rbf_get_models` - Load device models
- `rbf_get_repairs` - Load repair services
- `rbf_submit_booking` - Submit booking
- `rbf_get_booking_details` - Get booking details
- `rbf_get_invoice_data` - Get invoice data
- `rbf_delete_booking` - Delete booking

### **CSS Classes & Styling**
- **Admin Pages**: `.rbf-dashboard-page`, `.rbf-bookings-page`
- **Form Elements**: `.rbf-step`, `.rbf-brand-card`, `.rbf-model-card`
- **Cart System**: `.rbf-cart-item`, `.rbf-repair-item`
- **Responsive**: Mobile-first design with flexbox layouts

## 🎨 **Customization**

### **Styling Modifications**
All styles can be customized through the CSS files:
- `assets/css/admin.css` - Admin panel styling
- `assets/css/style.css` - Frontend form styling

### **Color Scheme**
- **Primary**: #017c36 (Green)
- **Secondary**: #30ab58 (Light Green)
- **Accent**: #6f42c1 (Purple)
- **Background**: #f8f9fa (Light Gray)

### **Icon Sizing**
```css
/* Admin icons - 30px */
.rbf-dashboard-page img[src*="emoji"],
.rbf-bookings-page td.media-icon img[src$=".svg"] {
    width: 30px !important;
    height: 30px !important;
}

/* Status icons - 40px */
.rbf-status-icon img {
    width: 40px !important;
    height: 40px !important;
}
```

## 📊 **Data Management**

### **JSON Data Structure**
```json
{
  "brands": {
    "Apple": {
      "models": [
        {
          "name": "iPhone 14",
          "description": "Latest iPhone model",
          "image": "/images/iphone14.jpg"
        }
      ]
    }
  }
}
```

### **Database Schema**
```sql
CREATE TABLE wp_rbf_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    service_type VARCHAR(50),
    selected_brand VARCHAR(100),
    selected_model VARCHAR(100),
    cart_items TEXT,
    subtotal DECIMAL(10,2),
    status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 🔒 **Security Features**

### **Nonce Verification**
- All AJAX requests include WordPress nonces
- Admin actions require proper user capabilities
- Input sanitization and validation

### **User Permissions**
- **Admin**: Full access to all features
- **Editor**: View bookings and basic management
- **Subscriber**: Submit bookings only

## 🚀 **Performance Features**

### **Optimization**
- **Lazy Loading**: Images load only when needed
- **Efficient Queries**: Optimized database queries
- **Caching**: Local storage for cart data
- **Minified Assets**: Compressed CSS and JavaScript

### **Mobile Optimization**
- **Touch-friendly**: Large touch targets
- **Responsive Design**: Adapts to all screen sizes
- **Fast Loading**: Optimized for mobile networks

## 📱 **Mobile Experience**

### **Responsive Design**
- **Mobile-first**: Designed for mobile devices first
- **Touch Interface**: Optimized for touch interactions
- **Fast Navigation**: Smooth transitions between steps
- **Offline Support**: Cart data saved locally

### **Progressive Web App Features**
- **Local Storage**: Cart persistence across sessions
- **Smooth Animations**: CSS transitions and animations
- **Native Feel**: App-like user experience

## 🔧 **Troubleshooting**

### **Common Issues**

#### **Modals Not Displaying**
```css
/* Ensure proper z-index and display */
.rbf-modal {
    z-index: 100000 !important;
    display: none !important;
}

.rbf-modal.show {
    display: flex !important;
}
```

#### **Icons Not Sizing Correctly**
```css
/* Force icon sizing */
.rbf-bookings-page img[src$=".svg"] {
    width: 25px !important;
    height: 25px !important;
}
```

#### **Radio Buttons Not Round**
```css
/* Ensure perfect circles */
.rbf-radio-custom {
    border-radius: 50% !important;
    width: 20px !important;
    height: 20px !important;
}
```

### **Debug Mode**
Enable debug logging by checking browser console for detailed error messages.

## 📈 **Future Enhancements**

### **Planned Features**
- **Payment Gateway Integration**: PayPal, Stripe support
- **Email Notifications**: Automated booking confirmations
- **SMS Integration**: Text message notifications
- **Analytics Dashboard**: Advanced reporting and insights
- **Multi-language Support**: Internationalization
- **API Endpoints**: REST API for external integrations

### **Custom Development**
The plugin is designed for easy extension and customization. Contact for custom development needs.

## 🤝 **Support & Contributing**

### **Getting Help**
- **Documentation**: Check this README first
- **Issues**: Report bugs through GitHub issues
- **Support**: Contact support team for assistance

### **Contributing**
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📄 **License**

This plugin is proprietary software. All rights reserved.

## 🏆 **Credits**

- **Developer**: EFIX Repair Services
- **Design**: Modern UI/UX with professional styling
- **Technology**: WordPress, PHP, JavaScript, CSS3

---

**Version**: 2.0.0  
**Last Updated**: January 2025  
**Compatibility**: WordPress 5.0+  
**Tested**: WordPress 6.4+  

For support and updates, visit: **www.efix.ae**
