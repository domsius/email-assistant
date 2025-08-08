#!/bin/bash

# Production setup script for Laravel Reverb
# Run this on your production server

echo "Setting up Laravel Reverb for production..."

# 1. Update .env file
echo "Updating .env configuration..."
cat >> .env << 'EOF'

# Laravel Reverb Configuration
BROADCAST_DRIVER=reverb
REVERB_APP_ID=466239
REVERB_APP_KEY=mog0mbu5yc4q8dviojkf
REVERB_APP_SECRET=xzsepf6gepdrqr2cw0oh
REVERB_HOST="srv806757.hstgr.cloud"
REVERB_PORT=443
REVERB_SCHEME=https

# Frontend configuration
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="srv806757.hstgr.cloud"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
EOF

# 2. Clear and rebuild caches
echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 3. Install supervisor configuration
echo "Installing supervisor configuration..."
sudo cp deploy/reverb-supervisor.conf /etc/supervisor/conf.d/reverb.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb

# 4. Build frontend assets
echo "Building frontend assets..."
npm install
npm run build

# 5. Test Reverb connection
echo "Testing Reverb..."
php artisan reverb:ping

echo "Setup complete! Check supervisor status:"
sudo supervisorctl status reverb