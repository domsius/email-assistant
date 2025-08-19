# Elasticsearch Production Setup Guide

## Quick Start Options

### 1. Managed Services (Easiest)

#### Elastic Cloud (Recommended)
1. Sign up at https://cloud.elastic.co
2. Create deployment (starts at ~$16/month)
3. Get credentials and update `.env`:
```env
ELASTICSEARCH_HOST=https://your-deployment.es.us-east-1.aws.cloud.es.io:9243
ELASTICSEARCH_API_KEY=your_api_key_here
ELASTICSEARCH_CLOUD_ID=your_cloud_id  # Optional
```

#### AWS OpenSearch
```env
ELASTICSEARCH_HOST=https://your-domain.us-east-1.es.amazonaws.com
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
```

### 2. Self-Hosted (VPS/Dedicated Server)

#### Using Docker (Recommended for small-medium deployments)
```bash
# Create docker-compose.production.yml
version: '3.8'
services:
  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.13.4
    container_name: elasticsearch_prod
    environment:
      - node.name=es01
      - cluster.name=production-cluster
      - discovery.type=single-node
      - bootstrap.memory_lock=true
      - "ES_JAVA_OPTS=-Xms1g -Xmx1g"  # Adjust based on server RAM
      - xpack.security.enabled=true
      - xpack.security.http.ssl.enabled=true
      - xpack.security.transport.ssl.enabled=true
      - ELASTIC_PASSWORD=${ELASTIC_PASSWORD}
    ulimits:
      memlock:
        soft: -1
        hard: -1
    volumes:
      - /data/elasticsearch:/usr/share/elasticsearch/data
      - ./certs:/usr/share/elasticsearch/config/certs
    ports:
      - 127.0.0.1:9200:9200  # Only expose locally, use nginx proxy
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "-u", "elastic:${ELASTIC_PASSWORD}", "http://localhost:9200"]
      interval: 30s
      timeout: 10s
      retries: 5

# Start with:
docker-compose -f docker-compose.production.yml up -d
```

#### Native Installation (Ubuntu/Debian)
```bash
# 1. Add Elasticsearch repository
wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -
echo "deb https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list

# 2. Install
sudo apt update && sudo apt install elasticsearch

# 3. Configure /etc/elasticsearch/elasticsearch.yml
network.host: 127.0.0.1
xpack.security.enabled: true
xpack.security.http.ssl.enabled: true

# 4. Set password
sudo /usr/share/elasticsearch/bin/elasticsearch-reset-password -u elastic

# 5. Start service
sudo systemctl enable elasticsearch
sudo systemctl start elasticsearch
```

### 3. Security Best Practices

#### Nginx Reverse Proxy with SSL
```nginx
server {
    listen 443 ssl http2;
    server_name es.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/es.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/es.yourdomain.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:9200;
        proxy_http_version 1.1;
        proxy_set_header Connection "Keep-Alive";
        proxy_set_header Proxy-Connection "Keep-Alive";
        
        # Basic auth
        auth_basic "Elasticsearch";
        auth_basic_user_file /etc/nginx/.htpasswd;
        
        # IP whitelist (optional)
        allow YOUR_APP_SERVER_IP;
        deny all;
    }
}
```

#### Firewall Rules
```bash
# Only allow Elasticsearch from localhost and your app server
sudo ufw allow from YOUR_APP_SERVER_IP to any port 9200
sudo ufw deny 9200
```

### 4. Laravel Configuration

Update your `.env.production`:
```env
# For managed services
ELASTICSEARCH_HOST=https://your-cluster.elastic.co:9243
ELASTICSEARCH_USERNAME=elastic
ELASTICSEARCH_PASSWORD=your_secure_password

# For self-hosted with SSL
ELASTICSEARCH_HOST=https://es.yourdomain.com
ELASTICSEARCH_USERNAME=elastic
ELASTICSEARCH_PASSWORD=your_secure_password
ELASTICSEARCH_SSL_VERIFICATION=true

# For self-hosted without SSL (internal network only!)
ELASTICSEARCH_HOST=http://YOUR_ES_SERVER_IP:9200
ELASTICSEARCH_USERNAME=elastic
ELASTICSEARCH_PASSWORD=your_secure_password
```

### 5. Performance Tuning

#### Memory Settings
- **Small (1-2GB RAM)**: ES_JAVA_OPTS="-Xms512m -Xmx512m"
- **Medium (4-8GB RAM)**: ES_JAVA_OPTS="-Xms2g -Xmx2g"
- **Large (16GB+ RAM)**: ES_JAVA_OPTS="-Xms8g -Xmx8g"

Rule: Allocate 50% of available RAM, but no more than 32GB

#### Index Settings for Email Data
```json
{
  "settings": {
    "number_of_shards": 1,
    "number_of_replicas": 0,
    "index": {
      "refresh_interval": "30s"
    }
  },
  "mappings": {
    "properties": {
      "subject": { "type": "text", "analyzer": "standard" },
      "body": { "type": "text", "analyzer": "standard" },
      "sender_email": { "type": "keyword" },
      "received_at": { "type": "date" },
      "embedding": { "type": "dense_vector", "dims": 1536 }
    }
  }
}
```

### 6. Monitoring

#### Health Check Endpoint
```bash
curl -u elastic:password https://es.yourdomain.com/_cluster/health?pretty
```

#### Laravel Health Check
```php
// app/Console/Commands/CheckElasticsearch.php
public function handle()
{
    try {
        $health = $this->elasticsearch->indices()->stats();
        $this->info('Elasticsearch is healthy');
        return 0;
    } catch (\Exception $e) {
        $this->error('Elasticsearch is down: ' . $e->getMessage());
        return 1;
    }
}
```

### 7. Backup Strategy

```bash
# Daily snapshots
0 2 * * * docker exec elasticsearch_prod curl -X PUT "localhost:9200/_snapshot/backup/snapshot_$(date +\%Y\%m\%d)?wait_for_completion=false"

# Or use Elastic Cloud's automated backups
```

### 8. Cost Estimates

- **Elastic Cloud**: $16-95/month (managed, includes backups)
- **AWS OpenSearch**: $25-200/month (t3.small.search to m5.large)
- **DigitalOcean**: $15-60/month (Basic to Production tier)
- **Self-hosted VPS**: $5-40/month (requires maintenance)

### 9. Migration from Development

```bash
# Export data from local
docker exec elasticsearch curl -X POST "localhost:9200/_reindex" -H 'Content-Type: application/json' -d'{
  "source": {"index": "emails"},
  "dest": {"index": "emails_backup"}
}'

# Import to production (adjust based on your setup)
php artisan tinker
>>> \App\Services\ElasticsearchService::reindexAllEmails();
```

### 10. Troubleshooting

#### Connection Issues
```bash
# Test from Laravel app server
curl -u elastic:password https://es.yourdomain.com

# Check logs
docker logs elasticsearch_prod
# or
sudo journalctl -u elasticsearch
```

#### Memory Issues
```bash
# Increase system limits
echo "vm.max_map_count=262144" | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

## Quick Decision Guide

Choose **Elastic Cloud** if:
- You want zero maintenance
- Budget allows ($16+/month)
- Need enterprise features

Choose **Self-hosted Docker** if:
- Comfortable with server management
- Want full control
- Budget conscious

Choose **AWS/DigitalOcean Managed** if:
- Already using their infrastructure
- Want balance of control and convenience