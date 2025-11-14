#!/bin/sh

# ZeroTier Network Info Command

if command -v zerotier-cli >/dev/null 2>&1; then
    echo "ZeroTier Network Information:"
    echo "=============================="
    echo ""
    zerotier-cli info 2>/dev/null || sudo zerotier-cli info
    echo ""
    echo "Joined Networks:"
    echo "================"
    zerotier-cli listnetworks 2>/dev/null || sudo zerotier-cli listnetworks
else
    echo "Error: ZeroTier CLI not found. Is ZeroTier installed?"
    exit 1
fi

