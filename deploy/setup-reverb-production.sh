#!/bin/bash

# Production setup script for Laravel Reverb
# Run this on your production server

echo "Setting up Laravel Reverb for production..."

# 1. Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "Error: Please run this script from your Laravel project root"
    exit 1
fi

# 2. Update .env file if needed
echo "Checking .env configuration..."
if ! grep -q "REVERB_APP_ID" .env; then
    echo "Adding Reverb configuration to .env..."
    cat >> .env << 'EOF'

# Laravel Reverb Configuration
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=466239
REVERB_APP_KEY=mog0mbu5yc4q8dviojkf
REVERB_APP_SECRET=xzsepf6gepdrqr2cw0oh
REVERB_HOST="srv806757.hstgr.cloud"
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Frontend configuration
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="srv806757.hstgr.cloud"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
EOF
else
    echo "Reverb already configured in .env"
fi

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