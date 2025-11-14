#!/bin/bash

# ZeroTier-FppPlugin install script

# Include common scripts functions and variables
. ${FPPDIR}/scripts/common

PLUGIN_DIR="${MEDIADIR}/plugins/ZeroTier-FppPlugin"

# Install ZeroTier if not already installed
if ! command -v zerotier-cli >/dev/null 2>&1; then
    echo "Installing ZeroTier..."
    curl -s https://install.zerotier.com | sudo bash
    
    # Start and enable ZeroTier service
    sudo systemctl start zerotier-one
    sudo systemctl enable zerotier-one
    
    # Wait for ZeroTier service to be ready
    echo "Waiting for ZeroTier service to start..."
    sleep 5
    
    # Check if ZeroTier CLI is now available
    if ! command -v zerotier-cli >/dev/null 2>&1; then
        echo "Warning: ZeroTier installation may have failed. Please check manually."
    else
        echo "ZeroTier installed successfully"
    fi
else
    echo "ZeroTier is already installed"
fi

# Install shownet command
if [ -f "${PLUGIN_DIR}/scripts/shownet.sh" ]; then
    sudo cp "${PLUGIN_DIR}/scripts/shownet.sh" /usr/local/bin/shownet
    sudo chmod +x /usr/local/bin/shownet
    echo "Installed 'shownet' command"
fi

echo "ZeroTier plugin installation complete!"

