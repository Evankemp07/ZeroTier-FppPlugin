# fpp-plugin-ZeroTier

ZeroTier Network Manager plugin for FPP (Falcon Player).

This plugin provides ZeroTier network management capabilities for FPP, allowing you to easily connect your Raspberry Pi to ZeroTier networks and manage network connections through the FPP web interface.

## Features

- **Automatic Installation**: Installs ZeroTier automatically when the plugin is installed
- **Web Interface**: Manage ZeroTier networks through the FPP web UI
- **Status Monitoring**: View network status, IP addresses, and connection information
- **Easy Network Management**: Join and leave networks with a simple interface
- **Command Line Tool**: Includes the `shownet` command for advanced users

## Installation

1. Install the plugin through the FPP Plugin Manager
2. The plugin will automatically install ZeroTier if needed
3. Open the plugin to view your node information
4. Enter a network id to join networks

## Usage

### Web Interface

View your ZeroTier node ID, version, status, and joined networks with detailed information (IP addresses, interface, network type). 
Join new networks, leave existing networks, check for ZeroTier updates, and update ZeroTier with one click.

### Command Line

The plugin installs a `shownet` command for command-line usage:

```bash
shownet info      # Show network information
shownet join      # Join a network (will prompt for Network ID)
shownet leave     # Leave a network (will prompt for Network ID)
```

## Requirements

- FPP version 9.0 or higher
- Raspberry Pi or compatible Linux system
- Internet connection for ZeroTier installation

## Links

- [ZeroTier Website](https://www.zerotier.com/)
- [ZeroTier Central](https://accounts.zerotier.com/realms/zerotier/protocol/openid-connect/auth?client_id=central-v2)
- [ZeroTier Documentation](https://docs.zerotier.com/)
