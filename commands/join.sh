#!/bin/sh

# ZeroTier Join Network Command

NETWORK_ID="$1"

if [ -z "$NETWORK_ID" ]; then
    echo "Error: Network ID required"
    echo "Usage: join.sh <network-id>"
    exit 1
fi

if ! command -v zerotier-cli >/dev/null 2>&1; then
    echo "Error: ZeroTier CLI not found. Is ZeroTier installed?"
    exit 1
fi

echo "Joining ZeroTier network: $NETWORK_ID"
zerotier-cli join "$NETWORK_ID" 2>/dev/null || sudo zerotier-cli join "$NETWORK_ID"

if [ $? -eq 0 ]; then
    echo "Successfully joined network: $NETWORK_ID"
    echo ""
    echo "Note: You may need to authorize this device in ZeroTier Central:"
    echo "https://my.zerotier.com/network/$NETWORK_ID"
else
    echo "Failed to join network: $NETWORK_ID"
    exit 1
fi

