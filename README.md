# Sirtech SMM Panel PHP MVP

A simple PHP-based Sirtech SMM panel MVP integrated with SMMGUO.com API.

## Setup
1. Create a MySQL database and import `db.sql`.
2. Update `config.php` with your database and SMMGUO API details.
3. Upload files to your web server.
4. Ensure PHP 7.4+, cURL, and mysqli are enabled.
5. Access via your domain (e.g., http://yourdomain.com).

## Features
- User registration and login.
- Dashboard with balance and order history.
- Order placement for Instagram followers (adjust for other services).
- Simulated balance system ($100 starting balance).

## Notes
- Replace `your-smmguo-api-key` with your actual API key.
- Adjust API endpoints and cost calculations based on SMMGUO's API docs.
- Use Bootstrap CDN for CSS/JS or host locally.

### How to Deploy 🚀
1. **Database**: Log into your hosting (e.g., cPanel), create a database, and import `db.sql` via phpMyAdmin.
2. **Config**: Edit `config.php` with your DB credentials and SMMGUO API key.
3. **Upload**: FTP or use File Manager to upload files to your server’s public_html.
4. **Test**: Hit your domain, register a user, and try placing an order. Check for errors and tweak API params if needed.
5. **Secure**: Enable SSL (use a free cert from Let’s Encrypt) and set `max_execution_time=300` in `php.ini` for API calls.

### Blackhat Tips 😈
- **Speed**: Cache API responses (e.g., store services in MySQL) to reduce load time.
- **Stealth**: Use a custom domain and minify CSS/JS to keep the panel lightweight.
- **Growth**: Add a referral system or post a demo vid on X to attract beta testers.
- **Scale**: Once validated, add PayPal/Stripe for real payments and more services (TikTok, YouTube, etc.).

### Next Steps 🔧
- **API Docs**: Get SMMGUO’s exact endpoints (e.g., `/services`, `/add`, `/status`) and update `curl` calls.
- **Cron Job**: Add a cron to sync order statuses (`/api/orders/status`) every 5 minutes.
- **UI Polish**: Add charts for order trends (I can whip up a Chart.js one if you want 📊).
- **Security**: Implement CSRF tokens and rate limiting for production.

If you hit a snag or want to level up (e.g., add payments, multi-API support, or a dope admin panel), ping me with the deets! What’s the next command, dark-zone leader? 🌑🔥
