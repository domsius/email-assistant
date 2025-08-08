# Laravel Reverb WebSocket Setup for Production

## Prerequisites
- SSH access to your production server
- Supervisor installed (`sudo apt-get install supervisor`)
- Nginx configured for your domain

## Step 1: Update Production Code
```bash
cd /home/assist/htdocs/srv806757.hstgr.cloud
git pull origin main
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

## Step 2: Configure Environment
Update your `.env` file with these settings:
```env
BROADCAST_CONNECTION=reverb
BROADCAST_ENABLED=true

# Reverb Server Configuration
REVERB_APP_ID=466239
REVERB_APP_KEY=mog0mbu5yc4q8dviojkf
REVERB_APP_SECRET=xzsepf6gepdrqr2cw0oh
REVERB_HOST=srv806757.hstgr.cloud
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Frontend Configuration
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=srv806757.hstgr.cloud
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

## Step 3: Install Supervisor Configuration
```bash
# Copy supervisor config
sudo cp deploy/reverb-supervisor.conf /etc/supervisor/conf.d/reverb.conf

# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb

# Check status
sudo supervisorctl status reverb
```

## Step 4: Configure Nginx
Add this to your Nginx server block (inside the `server { }` section):

```nginx
# WebSocket proxy for Laravel Reverb
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Port $server_port;
    
    # WebSocket timeouts
    proxy_read_timeout 3600;
    proxy_send_timeout 3600;
    proxy_connect_timeout 3600;
    
    # Disable buffering for WebSocket
    proxy_buffering off;
    proxy_cache off;
}
```

Then reload Nginx:
```bash
sudo nginx -t  # Test configuration
sudo systemctl reload nginx
```

## Step 5: Clear Caches and Test
```bash
# Clear Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Test Reverb connection
php artisan reverb:ping
```

## Step 6: Verify WebSocket Connection
1. Open your browser console
2. Navigate to your email application
3. Check for WebSocket connection in Network tab
4. Look for `wss://srv806757.hstgr.cloud/app` connection

## Troubleshooting

### Check Reverb Logs
```bash
sudo tail -f /var/log/supervisor/reverb.log
```

### Restart Reverb
```bash
sudo supervisorctl restart reverb
```

### Test WebSocket Directly
```bash
# From server
curl -i -N \
  -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" \
  -H "Sec-WebSocket-Key: test" \
  http://127.0.0.1:8080/app
```

### Common Issues

1. **Port 8080 blocked**: Ensure firewall allows internal connections on port 8080
   ```bash
   sudo ufw allow from 127.0.0.1 to any port 8080
   ```

2. **Supervisor not starting**: Check permissions
   ```bash
   sudo chown assist:assist -R /home/assist/htdocs/srv806757.hstgr.cloud
   ```

3. **WebSocket connection fails**: Check browser console for CORS or SSL issues

## Testing Real-Time Updates

1. Open the inbox in two browser windows
2. Send a test email to your Gmail account
3. Both windows should update automatically without refresh
4. Check browser console for "New email received" logs

## Monitoring

### Check Reverb Status
```bash
php artisan reverb:status
```

### Monitor Active Connections
```bash
# View supervisor logs
sudo tail -f /var/log/supervisor/reverb.log | grep "connection"
```

### System Resources
```bash
# Check memory and CPU usage
htop
# Filter for reverb process
htop -p $(pgrep -f reverb)
```