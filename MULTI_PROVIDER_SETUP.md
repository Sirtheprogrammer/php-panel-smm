# Multi-Provider API Setup Guide

## Overview
Your SMM panel now supports multiple API providers with automatic failover, load balancing, and performance tracking. This ensures maximum uptime and reliability for your services.

## Setup Instructions

### 1. Database Setup
Run the multi-provider database schema to add the necessary tables:

```sql
-- Execute this SQL file to add multi-provider support
SOURCE db_multi_provider.sql;
```

Or manually run the SQL commands from `db_multi_provider.sql` in your MySQL database.

### 2. Default Provider
The system automatically migrates your existing SMMGUO provider as the default provider with priority 1.

### 3. Adding New Providers
1. Go to Admin Panel → API Providers
2. Use the "Add New API Provider" form
3. Fill in:
   - **Provider Name**: Unique name (e.g., "SMM Provider 2")
   - **API URL**: Full API endpoint URL
   - **API Key**: Your API key for that provider
   - **Priority**: Lower numbers = higher priority (1 is highest)

### 4. Managing Providers
- **Sync Services**: Import/update services from a provider
- **Activate/Deactivate**: Enable/disable providers
- **Test Connection**: Verify provider connectivity
- **Delete**: Remove providers (only if no associated services)

## Features

### 🔄 Automatic Failover
- If primary provider fails, system automatically tries backup providers
- Seamless switching without user intervention
- Failure logs for troubleshooting

### 📊 Performance Tracking
- Success rates for each provider
- Response time monitoring
- Daily performance statistics
- Provider health dashboard

### 🎯 Load Balancing
- Priority-based provider selection
- Intelligent routing based on provider performance
- Automatic provider ranking updates

### 🔧 Service Management
- Import services from multiple providers
- Cross-provider service mapping
- Provider-specific service configurations
- Bulk service operations

## How It Works

### Order Placement
1. User places order for a service
2. System identifies primary provider for that service
3. Attempts order with primary provider
4. If primary fails, tries backup providers automatically
5. Logs failover events for analysis

### Service Import
1. Admin clicks "Sync Services" for a provider
2. System fetches all services from provider API
3. Updates existing services or creates new ones
4. Tracks import statistics and errors

### Provider Health Monitoring
- Continuous monitoring of provider performance
- Automatic success rate calculations
- Response time tracking
- Failure detection and logging

## Admin Interface

### Provider Management
- **Dashboard**: Overview of all providers and their status
- **Performance Stats**: 7-day performance metrics
- **Real-time Status**: Live provider health indicators
- **Bulk Operations**: Manage multiple providers at once

### Service Management
- Services now show which provider they belong to
- Import services from specific providers
- Cross-provider service equivalency mapping
- Provider-specific pricing and configurations

## API Endpoints (AJAX)

### Provider Management
- `add_provider`: Add new API provider
- `toggle_provider_status`: Activate/deactivate provider
- `delete_provider`: Remove provider
- `sync_provider_services`: Import services from provider
- `test_provider_connection`: Test provider connectivity

### Enhanced Order Management
- Orders now use multi-provider system with automatic failover
- Order status refresh works across all providers
- Provider information included in order details

## Database Schema

### New Tables
- `api_providers`: Store provider configurations
- `provider_performance`: Track provider statistics
- `provider_failover_logs`: Log failover events
- `provider_service_mapping`: Cross-provider service mapping
- `service_sync_logs`: Track service import history

### Updated Tables
- `services`: Added provider_id and additional fields
- `orders`: Added provider_id and api_order_id fields

## Best Practices

### Provider Priority
- Set most reliable providers with priority 1-3
- Use less reliable providers as backups (priority 4+)
- Regularly review and adjust priorities based on performance

### Service Mapping
- Map equivalent services across providers for better failover
- Use similar service names and categories
- Regular sync to keep services updated

### Monitoring
- Check provider performance dashboard regularly
- Review failover logs to identify problematic providers
- Monitor success rates and response times

## Troubleshooting

### Provider Connection Issues
1. Use "Test Connection" button to diagnose
2. Verify API URL and key are correct
3. Check provider status (active/inactive)
4. Review error logs in browser console

### Service Import Problems
1. Check provider API key permissions
2. Verify API endpoint is accessible
3. Review sync logs for specific errors
4. Ensure database has sufficient storage

### Order Failures
1. Check if all providers are down
2. Review failover logs for patterns
3. Verify service exists on backup providers
4. Check user balance and service availability

## Security Notes

- API keys are stored securely in the database
- All provider communications use HTTPS
- Session-based authentication for admin functions
- Input validation on all provider management operations

## Performance Optimization

- Provider performance is cached and updated periodically
- Failed providers are temporarily deprioritized
- Response times are tracked for optimal routing
- Database queries are optimized for multi-provider operations

## Support

If you encounter any issues:
1. Check the browser console for JavaScript errors
2. Review PHP error logs
3. Verify database schema is properly updated
4. Test individual provider connections
5. Check failover logs for patterns

Your SMM panel now has enterprise-level reliability with automatic failover and comprehensive provider management!
