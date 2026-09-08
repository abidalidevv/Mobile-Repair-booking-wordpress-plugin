# 🚀 Repair Booking Form Plugin - Quick Reference Guide

**Essential Commands, Workflows & Troubleshooting for Administrators**

## 📋 Quick Actions

### **Dashboard Operations**
- **Refresh Dashboard**: Click "Refresh Dashboard Data" button
- **Auto-refresh**: Dashboard updates every 10 seconds automatically
- **Status Update**: Click on booking status to change it quickly

### **Price Management**
- **Individual Price Edit**: Click on price in table → Edit → Press Enter (auto-saves)
- **Bulk Price Update**: Use bulk controls at top of pricing page
- **Filter Table**: Use Brand → Model → Repair dropdowns to find specific items
- **Regenerate Combinations**: Use "Regenerate All Combinations" button

### **Database Operations**
- **Setup Database**: Click "Setup Database Tables" (first time only)
- **Fix Issues**: Click "Fix Database Issues" if problems occur
- **Sync Data**: Use "Regenerate All Combinations" to sync JSON with database

---

## 🔧 Common Admin Tasks

### **Adding New Repair Service**
1. Go to **Repair Services Management**
2. Click **"Add New Repair"**
3. Enter name and base price
4. Save → Automatically syncs to pricing tables

### **Updating Brand Prices**
1. Go to **Repair Prices Management**
2. Use **Brand filter** to select specific brand
3. Use **Bulk Brand Update** section
4. Enter new price and click "Apply"

### **Managing Admin Price Overrides**
1. **Edit Individual Price**: Click on price in table → Edit → Save
2. **View Overrides**: Use "Only Admin Overrides" filter toggle
3. **Preserve During Regeneration**: System automatically preserves custom prices

### **Handling New Bookings**
1. **Dashboard View**: New bookings appear in "Pending" section
2. **Status Update**: Click status to change (Pending → In Progress → Completed)
3. **Details View**: Click on booking to see full information

---

## 🚨 Troubleshooting Quick Fixes

### **Dashboard Not Updating**
```bash
# Check if auto-refresh is working
- Look for "Last updated" timestamp
- Check browser console for JavaScript errors
- Click "Refresh Dashboard Data" manually
```

### **Prices Showing as 0.00**
```bash
# Quick fix sequence
1. Go to Repair Prices Management
2. Click "Regenerate All Combinations"
3. Wait for completion message
4. Verify prices are now correct
```

### **Filter Not Working**
```bash
# Common solutions
1. Clear browser cache (Ctrl+F5)
2. Check JavaScript console for errors
3. Verify table has data-brand-id attributes
4. Try different filter combinations
```

### **Admin Prices Lost**
```bash
# Check preservation system
1. Verify is_admin_edited column exists
2. Check error logs for regeneration issues
3. Use "Regenerate All Combinations" to restore
4. Contact support if issue persists
```

---

## 📊 Database Queries for Debugging

### **Check Booking Statuses**
```sql
SELECT status, COUNT(*) as count 
FROM rbf_bookings 
GROUP BY status;
```

### **Verify Admin Overrides**
```sql
SELECT COUNT(*) as admin_prices 
FROM rbf_pricing 
WHERE is_admin_edited = 1;
```

### **Check Repair Prices**
```sql
SELECT name, price, status 
FROM rbf_repairs 
WHERE status != 'deleted';
```

### **Verify Pricing Combinations**
```sql
SELECT 
    b.name as brand,
    m.name as model,
    r.name as repair,
    p.price,
    p.is_admin_edited
FROM rbf_pricing p
JOIN rbf_brands b ON p.brand_id = b.id
JOIN rbf_models m ON p.model_id = m.id
JOIN rbf_repairs r ON p.repair_id = r.id
LIMIT 10;
```

---

## 🎯 Key Workflows Summary

### **Daily Operations**
1. **Check Dashboard** → Review pending bookings and status counts
2. **Process Bookings** → Update statuses as work progresses
3. **Manage Prices** → Adjust prices as needed using filters
4. **Monitor System** → Check for any error messages or issues

### **Weekly Maintenance**
1. **Verify Data Sync** → Ensure JSON and database are synchronized
2. **Check Admin Overrides** → Verify custom prices are preserved
3. **Review Performance** → Monitor regeneration times and system response
4. **Update Services** → Add/modify repair services as business needs change

### **Monthly Tasks**
1. **Database Backup** → Backup before major changes
2. **Performance Review** → Check for optimization opportunities
3. **User Training** → Update team on new features or workflows
4. **System Updates** → Keep plugin and WordPress updated

---

## 🔑 Essential Keyboard Shortcuts

### **Table Navigation**
- **Tab**: Move between form fields
- **Enter**: Save price changes (auto-save)
- **Escape**: Cancel current edit
- **Ctrl+F**: Find specific items in table

### **Filter Operations**
- **Dropdown Selection**: Use arrow keys to navigate options
- **Apply Filter**: Press Enter or click "Apply Filter" button
- **Clear Filter**: Use "Clear Filter" button to reset all filters

---

## 📱 Mobile Admin Access

### **Responsive Features**
- **Dashboard Cards**: Automatically stack on mobile devices
- **Table Scrolling**: Horizontal scroll for pricing tables
- **Touch-Friendly**: All buttons and inputs optimized for touch
- **Mobile Menu**: Collapsible admin menu for small screens

### **Mobile Best Practices**
1. **Use Landscape Mode** for pricing tables
2. **Tap and Hold** for context menus
3. **Swipe** to navigate between sections
4. **Pinch to Zoom** for detailed views

---

## 🚀 Performance Tips

### **Large Dataset Management**
- **Use Filters**: Always filter before editing large tables
- **Batch Operations**: Use bulk update features instead of individual edits
- **Monitor Progress**: Watch for completion messages during regeneration
- **Schedule Regenerations**: Run during low-traffic periods

### **Browser Optimization**
- **Clear Cache**: Regularly clear browser cache
- **Close Tabs**: Don't keep too many admin tabs open
- **Update Browser**: Use latest browser versions
- **Disable Extensions**: Some extensions can interfere with admin interface

---

## 📞 Emergency Procedures

### **System Not Responding**
1. **Check Browser Console** for JavaScript errors
2. **Refresh Page** (F5 or Ctrl+R)
3. **Clear Browser Cache** (Ctrl+Shift+Delete)
4. **Try Different Browser** to isolate issue

### **Data Loss or Corruption**
1. **Don't Panic** - Admin overrides are usually preserved
2. **Check Error Logs** in error-logger.php
3. **Use "Fix Database"** function
4. **Contact Support** with error details

### **Performance Issues**
1. **Check PHP Memory Limit** (should be 256M+)
2. **Verify Execution Time** (should be 300s+)
3. **Monitor Database Size** and optimize if needed
4. **Check Server Resources** (CPU, RAM, disk space)

---

## 📋 Quick Checklist

### **Before Making Changes**
- [ ] Backup database
- [ ] Test in staging environment
- [ ] Verify current system state
- [ ] Plan rollback strategy

### **After Making Changes**
- [ ] Verify functionality works
- [ ] Check admin overrides preserved
- [ ] Test filtering and search
- [ ] Monitor error logs

### **Regular Maintenance**
- [ ] Check error logs weekly
- [ ] Verify data sync monthly
- [ ] Monitor performance quarterly
- [ ] Update documentation annually

---

**Quick Reference Version**: 1.1  
**Last Updated**: Current Date  
**For Full Documentation**: See `admin-documentation.md`  
**For File Details**: See `file-documentation.md`
