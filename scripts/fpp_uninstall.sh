#!/bin/bash

# fpp-plugin-ZeroTier uninstall script

# Include common scripts functions and variables
. ${FPPDIR}/scripts/common

PLUGIN_DIR="${MEDIADIR}/plugins/fpp-plugin-ZeroTier"

# Set flag to run uninstall non-interactively
export FPP_UNINSTALL=1

# Run the shownet uninstall function to remove ZeroTier
if [ -f "${PLUGIN_DIR}/scripts/shownet.sh" ]; then
    # Source the script to get the functions
    . "${PLUGIN_DIR}/scripts/shownet.sh"
    
    # Call the uninstall function (it will detect OS and uninstall ZeroTier)
    shownet_uninstall
else
    # Fallback: manual removal if script not found
    echo "Warning: shownet.sh not found, attempting manual uninstall..."
    
    # Remove shownet command
    if [ -f /usr/local/bin/shownet ]; then
        sudo rm -f /usr/local/bin/shownet
        echo "Removed 'shownet' command"
    fi
    
    # Stop and disable ZeroTier service
    if systemctl is-active --quiet zerotier-one 2>/dev/null; then
        sudo systemctl stop zerotier-one 2>/dev/null || true
        sudo systemctl disable zerotier-one 2>/dev/null || true
        echo "ZeroTier service stopped and disabled"
    fi
    
    # Uninstall ZeroTier package
    if command -v apt-get >/dev/null 2>&1; then
        if dpkg -l | grep -q zerotier-one 2>/dev/null; then
            sudo apt-get remove -y zerotier-one 2>/dev/null && \
                echo "ZeroTier uninstalled via apt-get" || \
                echo "Warning: Could not uninstall ZeroTier via apt-get"
        fi
    elif command -v yum >/dev/null 2>&1; then
        if rpm -q zerotier-one >/dev/null 2>&1; then
            sudo yum remove -y zerotier-one 2>/dev/null && \
                echo "ZeroTier uninstalled via yum" || \
                echo "Warning: Could not uninstall ZeroTier via yum"
        fi
    fi
fi

echo "ZeroTier plugin uninstallation complete!"

