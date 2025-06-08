# Distributed Streaming System

This document explains how to set up and use the distributed streaming system with Kafka, Liquidsoap, and Icecast.

## Architecture Overview

The system consists of several components that can run on the same server or be distributed across multiple servers:

- **API Server**: Handles user requests and stream control (Main Laravel API. The access points can be accessed by imorting the postma-collection.json into postman or the swagger-collection.yml in nto swagger)
- **Liquidsoap Server**: Manages audio streaming processes
- **Icecast Server**: Distributes audio streams to listeners
- **Kafka Server**: Handles message brokering between components
- **Metrics Server**: Collects and processes streaming metrics

## Server Roles

Each server can have one or more roles. Configure roles using the `SERVER_ROLES` environment variable:

```env
# Example: Server running API and Kafka
SERVER_ROLES=api,kafka

# Example: Server running Liquidsoap and Icecast
SERVER_ROLES=liquidsoap,icecast

# Example: Development environment (all components)
SERVER_ROLES=all
```

Available roles:
- `api`: API server handling user requests
- `liquidsoap`: Liquidsoap server handling audio streaming
- `icecast`: Icecast server for stream distribution
- `metrics`: Metrics collection for Icecast statistics
- `kafka`: Kafka message broker
- `all`: All-in-one server (development)

## Installation Requirements

1. PHP Requirements:
```bash
# Install PHP extensions
sudo apt-get install php-rdkafka
```

2. Composer Dependencies:
```bash
composer require kwn/php-rdkafka-stubs
composer require phpseclib/phpseclib
```

3. Liquidsoap Installation:
```bash
# On Ubuntu/Debian
sudo apt-get install liquidsoap

# On macOS
brew install liquidsoap
```

4. Icecast Installation:
```bash
# On Ubuntu/Debian
sudo apt-get install icecast2

# On macOS
brew install icecast
```

5. Kafka Installation:
```bash
# Download and extract Kafka
wget https://downloads.apache.org/kafka/3.5.0/kafka_2.13-3.5.0.tgz
tar -xzf kafka_2.13-3.5.0.tgz
cd kafka_2.13-3.5.0

# Start Zookeeper
bin/zookeeper-server-start.sh config/zookeeper.properties

# Start Kafka
bin/kafka-server-start.sh config/server.properties
```

## Configuration

1. Environment Variables:

```env
# Server Roles
SERVER_ROLES=all # or comma-separated list of roles

# Kafka Configuration
KAFKA_BROKER=172.24.49.76:9092
KAFKA_TOPIC_STREAM_EVENTS=skipcast-stream-events
KAFKA_TOPIC_LISTENER_METRICS=skipcast-listener-metrics
KAFKA_TOPIC_USER_INTERACTIONS=skipcast-user-interactions
KAFKA_CONSUMER_GROUP=skipcast-consumer-group
KAFKA_DEBUG=false
KAFKA_TIMEOUT=120000

# Liquidsoap Configuration
LIQUIDSOAP_HOST=localhost
LIQUIDSOAP_PORT=8000
LIQUIDSOAP_PASSWORD=hackme
LIQUIDSOAP_CONFIG_PATH=/var/www/liquidsoap/configs
LIQUIDSOAP_PLAYLIST_PATH=/var/www/liquidsoap/playlists
LIQUIDSOAP_MEDIA_PATH=/var/www/liquidsoap/media

# Liquidsoap SSH Configuration (for remote control)
LIQUIDSOAP_SSH_USER=liquidsoap
LIQUIDSOAP_SSH_KEY_PATH=/path/to/liquidsoap_rsa
```

2. Directory Structure:

```
/var/www/liquidsoap/
├── configs/
│   ├── public/
│   └── private/
├── playlists/
├── media/
└── pids/
```

Create the required directories:
```bash
mkdir -p /var/www/liquidsoap/{configs/{public,private},playlists,media,pids}
```

## Running the Services

1. Start the Kafka Consumer:
```bash
php artisan kafka:consume
```

2. Start the Metrics Collector:
```bash
php artisan icecast:metrics
```

3. Start the Liquidsoap Command Consumer:
```bash
php artisan liquidsoap:command-consumer
```

## API Endpoints

Stream Control:
```
POST /api/channels/{channel}/stream/start
POST /api/channels/{channel}/stream/stop
GET  /api/channels/{channel}/stream/status
```

Example Response:
```json
{
    "message": "Stream start command sent successfully",
    "channel": {
        "id": 1,
        "name": "My Channel",
        "slug": "my-channel",
        "state": "on"
    }
}
```

## Monitoring

1. Check Stream Status:
```bash
# View Kafka consumer logs
tail -f storage/logs/kafka-consumer.log

# View Icecast metrics logs
tail -f storage/logs/icecast-metrics.log

# View Liquidsoap logs
tail -f storage/logs/liquidsoap-command-consumer.log
```

2. Monitor Kafka Topics:
```bash
# List topics
kafka-topics.sh --list --bootstrap-server localhost:9092

# Monitor stream events
kafka-console-consumer.sh --bootstrap-server localhost:9092 --topic skipcast-stream-events --from-beginning

# Monitor listener metrics
kafka-console-consumer.sh --bootstrap-server localhost:9092 --topic skipcast-listener-metrics --from-beginning
```

## Troubleshooting

1. Check Service Status:
```bash
# Check Kafka status
systemctl status kafka

# Check Icecast status
systemctl status icecast2

# Check running Liquidsoap processes
ps aux | grep liquidsoap
```

2. Common Issues:

- **Stream won't start**: Check Liquidsoap logs and ensure the configuration file exists
- **No metrics**: Verify Icecast is running and accessible
- **Kafka connection issues**: Check network connectivity and broker address

## Security Considerations

1. SSH Keys:
- Generate a dedicated SSH key pair for Liquidsoap control
- Restrict the key's permissions on the server

2. Kafka Security:
- Enable SSL/TLS for Kafka communication
- Implement authentication for Kafka producers/consumers

3. Icecast Security:
- Use strong passwords
- Configure SSL for stream delivery

## Development Setup

For local development, you can run all components on a single machine:

1. Install all required software:
```bash
# Install PHP extensions
sudo apt-get install php-rdkafka

# Install Liquidsoap and Icecast
sudo apt-get install liquidsoap icecast2

# Install Kafka
wget https://downloads.apache.org/kafka/3.5.0/kafka_2.13-3.5.0.tgz
tar -xzf kafka_2.13-3.5.0.tgz
```

2. Configure environment:
```env
SERVER_ROLES=all
KAFKA_BROKER=localhost:9092
LIQUIDSOAP_HOST=localhost
```

3. Start services:
```bash
# Start Kafka
bin/zookeeper-server-start.sh config/zookeeper.properties
bin/kafka-server-start.sh config/server.properties

# Start Laravel services
php artisan kafka:consume
php artisan icecast:metrics
php artisan liquidsoap:command-consumer
```

## Production Deployment

For production, distribute components across multiple servers:

1. **API Server**:
```env
SERVER_ROLES=api
KAFKA_BROKER=172.24.49.76:9092
```

2. **Streaming Server**:
```env
SERVER_ROLES=liquidsoap,icecast
KAFKA_BROKER=172.24.49.76:9092
```

3. **Metrics Server**:
```env
SERVER_ROLES=metrics
KAFKA_BROKER=172.24.49.76:9092
```

Ensure all servers can communicate with the Kafka broker and have appropriate network access to other required services.
