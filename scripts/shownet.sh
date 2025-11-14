#!/bin/bash

# ZeroTier Setup and Management Script

# Usage:
#   ./shownet.sh        - Full setup
#   shownet info        - Show network info
#   shownet join        - Join network
#   shownet leave       - Leave network

SCRIPT_NAME=$(basename "$0")

if [ "$SCRIPT_NAME" = "shownet" ] || [ "$1" = "info" ] || [ "$1" = "join" ] || [ "$1" = "leave" ] || [ "$1" = "uninstall" ]; then
    SHOWNET_MODE=true
else
    SHOWNET_MODE=false
    set -e
fi

ZEROTIER_NETWORK_ID="${ZEROTIER_NETWORK_ID:-}"
ZEROTIER_API_TOKEN="${ZEROTIER_API_TOKEN:-}"

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

ZT_CLI_PATH=""
ZT_SUDO=""

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

resolve_zerotier_cli() {
    [ -n "$ZT_CLI_PATH" ] && return 0
    
    local candidates=(
        "${ZEROTIER_CLI:-}"
        "/usr/local/bin/zerotier-cli"
        "/opt/homebrew/bin/zerotier-cli"
    )
    
    command_exists zerotier-cli && candidates+=("$(command -v zerotier-cli)")
    
    for candidate in "${candidates[@]}"; do
        [ -z "$candidate" ] && continue
        command_exists "$candidate" || continue
        
        if "$candidate" info >/dev/null 2>&1; then
            ZT_CLI_PATH="$candidate"
            ZT_SUDO=""
            return 0
        fi
        
        if sudo "$candidate" info >/dev/null 2>&1; then
            ZT_CLI_PATH="$candidate"
            ZT_SUDO="sudo"
            return 0
        fi
    done
    return 1
}

run_zt_cli() {
    resolve_zerotier_cli || return 1
    [ -n "$ZT_SUDO" ] && $ZT_SUDO "$ZT_CLI_PATH" "$@" || "$ZT_CLI_PATH" "$@"
}

wait_for_cli() {
    local attempts="${1:-10}"
    local delay="${2:-2}"
    for ((attempt=1; attempt<=attempts; attempt++)); do
        resolve_zerotier_cli && return 0
        sleep "$delay"
    done
    return 1
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1" >&2
}

detect_os() {
    if [[ "$OSTYPE" == "darwin"* ]]; then
        OS="macos"
    elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
        OS="linux"
    else
        print_error "Unsupported OS: $OSTYPE"
        exit 1
    fi
}

install_zerotier() {
    if command_exists zerotier-cli; then
        return 0
    fi
    
    if [ "$OS" == "macos" ]; then
        if command_exists brew; then
            brew install zerotier-one
        else
            print_error "Homebrew not found. Install from: https://brew.sh"
            exit 1
        fi
    elif [ "$OS" == "linux" ]; then
        if command_exists apt-get || command_exists yum; then
            curl -s https://install.zerotier.com | sudo bash
        else
            print_error "Unsupported Linux package manager"
            exit 1
        fi
    fi
}

start_zerotier() {
    if [ "$OS" = "macos" ]; then
        if sudo launchctl list | grep -q com.zerotier.one; then
            :
        elif [ -f /Library/LaunchDaemons/com.zerotier.one.plist ]; then
            sudo launchctl load -w /Library/LaunchDaemons/com.zerotier.one.plist 2>/dev/null || \
            sudo launchctl load /Library/LaunchDaemons/com.zerotier.one.plist 2>/dev/null || true
        fi
        sleep 3
    elif [ "$OS" = "linux" ]; then
        sudo systemctl start zerotier-one
        sudo systemctl enable zerotier-one
        sleep 2
    fi
    
    wait_for_cli 10 2 || {
        print_error "ZeroTier CLI not available. Check service status."
        exit 1
    }
}

get_node_id() {
    if ! resolve_zerotier_cli; then
        print_error "ZeroTier CLI not found. Ensure service is running."
        exit 1
    fi
    
    local node_info
    if ! node_info=$(run_zt_cli info 2>/dev/null); then
        print_error "Could not get ZeroTier Node ID"
        exit 1
    fi
    
    ZEROTIER_NODE_ID=$(echo "$node_info" | awk '{print $3}')
    [ -z "$ZEROTIER_NODE_ID" ] || [ "$ZEROTIER_NODE_ID" = "info" ] && {
        print_error "Could not parse ZeroTier Node ID"
        exit 1
    }
}

join_network() {
    if ! resolve_zerotier_cli; then
        print_error "ZeroTier CLI not available"
        exit 1
    fi
    
    local networks
    if ! networks=$(run_zt_cli listnetworks 2>/dev/null); then
        print_error "Could not list ZeroTier networks"
        exit 1
    fi
    
    if echo "$networks" | grep -q "^${ZEROTIER_NETWORK_ID}[[:space:]]"; then
        return 0
    fi
    
    if run_zt_cli join "$ZEROTIER_NETWORK_ID" >/dev/null 2>&1; then
        return 0
    else
        print_error "Failed to join network $ZEROTIER_NETWORK_ID"
        exit 1
    fi
}

authorize_node() {
    [ -z "$ZEROTIER_API_TOKEN" ] && return 0
    
    curl -s -X POST \
        -H "Authorization: bearer $ZEROTIER_API_TOKEN" \
        "https://my.zerotier.com/api/network/$ZEROTIER_NETWORK_ID/member/$ZEROTIER_NODE_ID" \
        -d '{"config":{"authorized":true}}' >/dev/null 2>&1 || return 1
}

wait_for_ip() {
    if ! resolve_zerotier_cli; then
        print_error "ZeroTier CLI not available"
        exit 1
    fi
    
    ZEROTIER_IP=""
    ZEROTIER_NETWORK_INFO=""
    local ip_with_cidr=""
    
    for i in {1..30}; do
        local networks
        if networks=$(run_zt_cli listnetworks 2>/dev/null); then
            local network_line
            network_line=$(echo "$networks" | awk -v id="$ZEROTIER_NETWORK_ID" '$1 == id {print; exit}')
            if [ -n "$network_line" ]; then
                local ip_field
                ip_field=$(echo "$network_line" | awk '{print $8}')
                if [ -n "$ip_field" ] && [ "$ip_field" != "-" ]; then
                    ZEROTIER_NETWORK_INFO="$network_line"
                    ip_with_cidr="$ip_field"
                    ZEROTIER_IP=$(echo "$ip_field" | cut -d'/' -f1)
                fi
            fi
        fi
        [ -n "$ZEROTIER_IP" ] && break
        sleep 2
    done
    
    [ -z "$ZEROTIER_IP" ] && {
        print_error "No ZeroTier IP assigned. Node may need authorization."
        exit 1
    }
    
    NETWORK_NAME=$(echo "$ZEROTIER_NETWORK_INFO" | awk '{print $2}')
    local network_cidr
    network_cidr=$(echo "$ip_with_cidr" | cut -d'/' -f2)
    NETWORK_CIDR="${network_cidr:-24}"
}

get_zt_interface() {
    if [ "$OS" = "macos" ]; then
        ZT_INTERFACE=$(ifconfig | grep -B 1 "$ZEROTIER_IP" | grep -oE '^[a-z0-9]+' | head -1)
    elif [ "$OS" = "linux" ]; then
        ZT_INTERFACE=$(ip addr show | grep -B 2 "$ZEROTIER_IP" | grep -oE '^[0-9]+: [a-z0-9]+' | awk '{print $2}' | head -1)
    fi
    
    [ -z "$ZT_INTERFACE" ] && [ -n "$ZEROTIER_NETWORK_INFO" ] && \
        ZT_INTERFACE=$(echo "$ZEROTIER_NETWORK_INFO" | awk '{print $7}')
    [ -z "$ZT_INTERFACE" ] && resolve_zerotier_cli && {
        local network_line
        network_line=$(run_zt_cli listnetworks 2>/dev/null | awk -v id="$ZEROTIER_NETWORK_ID" '$1 == id {print; exit}')
        [ -n "$network_line" ] && ZT_INTERFACE=$(echo "$network_line" | awk '{print $7}')
    }
}

setup_routing() {
    [ -z "$ZEROTIER_IP" ] && return 0
    
    NETWORK_BASE=$(echo "$ZEROTIER_IP" | cut -d'.' -f1-3)
    NETWORK_RANGE="$NETWORK_BASE.0/${NETWORK_CIDR:-24}"
    
    if [ "$OS" == "linux" ] && [ -n "$ZT_INTERFACE" ]; then
        if ! ip route | grep -q "$NETWORK_RANGE"; then
            sudo ip route add "$NETWORK_RANGE" dev "$ZT_INTERFACE" 2>/dev/null && \
                print_success "Route added: $NETWORK_RANGE via $ZT_INTERFACE" || true
        fi
    elif [ "$OS" == "macos" ]; then
        # macOS ZeroTier handles routing automatically, but verify route exists
        if ! netstat -rn | grep -q "$NETWORK_BASE"; then
            # Route should be added automatically by ZeroTier
            # If not present, ZeroTier may still route via interface binding
            :
        fi
    fi
}

test_connectivity() {
    NETWORK_BASE=$(echo "$ZEROTIER_IP" | cut -d'.' -f1-3)
    GATEWAY_IP="$NETWORK_BASE.1"
    
    ping -c 1 -W 2 "$GATEWAY_IP" >/dev/null 2>&1 || true
}

install_shownet() {
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    THIS_SCRIPT="$SCRIPT_DIR/shownet.sh"
    
    if sudo cp "$THIS_SCRIPT" /usr/local/bin/shownet 2>/dev/null && \
       sudo chmod +x /usr/local/bin/shownet 2>/dev/null; then
        return 0
    fi
    return 1
}

shownet_info() {
    if ! resolve_zerotier_cli; then
        print_error "ZeroTier CLI not found or not accessible"
        exit 1
    fi
    
    echo "ZeroTier Network Information"
    echo "============================"
    echo ""
    
    local node_info
    if ! node_info=$(run_zt_cli info 2>/dev/null); then
        print_error "Could not get ZeroTier node info"
        exit 1
    fi
    
    local node_id version status
    node_id=$(echo "$node_info" | awk '{print $3}')
    version=$(echo "$node_info" | awk '{print $4}')
    status=$(echo "$node_info" | awk '{print $5}')
    
    echo "Node ID: $node_id"
    echo "Version: $version"
    echo "Status: $status"
    echo ""
    
    local all_networks
    if ! all_networks=$(run_zt_cli listnetworks 2>/dev/null); then
        print_error "Could not list ZeroTier networks"
        exit 1
    fi
    
    if echo "$all_networks" | grep -q '^[0-9a-f]'; then
        echo "Joined Networks:"
        echo "---------------"
        echo "$all_networks"
        echo ""
        
        if [ -n "$ZEROTIER_NETWORK_ID" ]; then
            local network_info
            network_info=$(echo "$all_networks" | awk -v id="$ZEROTIER_NETWORK_ID" '$1 == id {print; exit}')
            if [ -n "$network_info" ]; then
                local network_name network_status network_type network_interface network_ip
                network_name=$(echo "$network_info" | awk '{print $2}')
                network_status=$(echo "$network_info" | awk '{print $5}')
                network_type=$(echo "$network_info" | awk '{print $6}')
                network_interface=$(echo "$network_info" | awk '{print $7}')
                network_ip=$(echo "$network_info" | awk '{print $8}' | cut -d'/' -f1)
                
                echo "Network ID: $ZEROTIER_NETWORK_ID"
                echo "Network Name: $network_name"
                echo "Status: $network_status"
                echo "Type: $network_type"
                [ -n "$network_interface" ] && [ "$network_interface" != "-" ] && \
                    echo "Interface: $network_interface"
                [ -n "$network_ip" ] && [ "$network_ip" != "-" ] && \
                    echo "Your ZeroTier IP: $network_ip" || \
                    echo "IP: Not assigned (may need authorization)"
            fi
        fi
    else
        echo "Not currently joined to any networks"
        echo ""
        echo "To join a network, run: shownet join"
    fi
}

shownet_join() {
    if ! resolve_zerotier_cli; then
        print_error "ZeroTier CLI not found or not accessible"
        exit 1
    fi
    
    detect_os
    
    if [ -z "$ZEROTIER_NETWORK_ID" ]; then
        echo "Enter ZeroTier Network ID to join:"
        read -rp "Network ID: " ZEROTIER_NETWORK_ID
        [ -z "$ZEROTIER_NETWORK_ID" ] && {
            print_error "Network ID is required"
            exit 1
        }
    fi
    
    local networks
    if ! networks=$(run_zt_cli listnetworks 2>/dev/null); then
        print_error "Could not list ZeroTier networks"
        exit 1
    fi
    
    # Check if network is in the list (handle both "200 listnetworks <nwid>" and "<nwid>" formats)
    if echo "$networks" | grep -qE "(^| )${ZEROTIER_NETWORK_ID}([[:space:]]|$)"; then
        print_success "Already joined to network $ZEROTIER_NETWORK_ID"
        # Still set up routing in case it wasn't configured
        local network_line
        # Extract network line - handle both formats
        network_line=$(echo "$networks" | grep -E "(^| )${ZEROTIER_NETWORK_ID}([[:space:]]|$)" | head -1)
        # If line starts with "200 listnetworks", extract the actual network data
        if echo "$network_line" | grep -q "^200 listnetworks"; then
            network_line=$(echo "$network_line" | sed 's/^200 listnetworks //')
        fi
        if [ -n "$network_line" ]; then
            local ip_field
            ip_field=$(echo "$network_line" | awk '{print $8}')
            if [ -n "$ip_field" ] && [ "$ip_field" != "-" ]; then
                ZEROTIER_IP=$(echo "$ip_field" | cut -d'/' -f1)
                local network_cidr
                network_cidr=$(echo "$ip_field" | cut -d'/' -f2)
                NETWORK_CIDR="${network_cidr:-24}"
                ZEROTIER_NETWORK_INFO="$network_line"
                get_zt_interface
                setup_routing
            fi
        fi
        exit 0
    fi
    
    if run_zt_cli join "$ZEROTIER_NETWORK_ID" >/dev/null 2>&1; then
        print_success "Successfully joined network: $ZEROTIER_NETWORK_ID"
        local node_id
        node_id=$(run_zt_cli info 2>/dev/null | awk '{print $3}')
        echo "Authorize at: https://my.zerotier.com/network/$ZEROTIER_NETWORK_ID"
        echo "Node ID: $node_id"
        echo ""
        echo "Waiting for IP assignment..."
        
        # Wait for IP and set up routing (skip if running non-interactively for API)
        if [ -t 0 ] && [ -t 1 ]; then
            wait_for_ip
            get_zt_interface
            setup_routing
            
            print_success "ZeroTier IP: $ZEROTIER_IP"
            print_success "Network route configured"
        else
            # Non-interactive mode (API call) - just verify join succeeded
            sleep 2
            local verify_networks
            verify_networks=$(run_zt_cli listnetworks 2>/dev/null)
            if echo "$verify_networks" | grep -q "^${ZEROTIER_NETWORK_ID}[[:space:]]"; then
                print_success "Network join confirmed"
            fi
        fi
        exit 0
    else
        print_error "Failed to join network $ZEROTIER_NETWORK_ID"
        exit 1
    fi
}

shownet_leave() {
    if ! resolve_zerotier_cli; then
        print_error "ZeroTier CLI not found or not accessible"
        exit 1
    fi
    
    if [ -z "$ZEROTIER_NETWORK_ID" ]; then
        echo "Enter ZeroTier Network ID to leave:"
        read -rp "Network ID: " ZEROTIER_NETWORK_ID
        [ -z "$ZEROTIER_NETWORK_ID" ] && {
            print_error "Network ID is required"
            exit 1
        }
    fi
    
    local networks
    if ! networks=$(run_zt_cli listnetworks 2>/dev/null); then
        print_error "Could not list ZeroTier networks"
        exit 1
    fi
    
    # Check if network is in the list (handle both "200 listnetworks <nwid>" and "<nwid>" formats)
    if ! echo "$networks" | grep -qE "(^| )${ZEROTIER_NETWORK_ID}([[:space:]]|$)"; then
        print_success "Not currently joined to network: $ZEROTIER_NETWORK_ID"
        exit 0
    fi
    
    if run_zt_cli leave "$ZEROTIER_NETWORK_ID" >/dev/null 2>&1; then
        # Verify it actually left
        sleep 1
        local verify_networks
        verify_networks=$(run_zt_cli listnetworks 2>/dev/null)
        if ! echo "$verify_networks" | grep -qE "(^| )${ZEROTIER_NETWORK_ID}([[:space:]]|$)"; then
            print_success "Successfully left network: $ZEROTIER_NETWORK_ID"
            exit 0
        else
            print_error "Leave command succeeded but network still in list"
            exit 1
        fi
    else
        print_error "Failed to leave network $ZEROTIER_NETWORK_ID"
        exit 1
    fi
}

shownet_uninstall() {
    echo "ZeroTier Uninstall"
    echo "=================="
    echo ""
    echo "This will remove the 'shownet' command, stop ZeroTier service, and uninstall ZeroTier."
    echo ""
    
    # Check if running non-interactively (from fpp_uninstall.sh)
    if [ -z "${FPP_UNINSTALL:-}" ]; then
        read -p "Are you sure? (type 'yes' to confirm): " CONFIRM1
        [ "$CONFIRM1" != "yes" ] && {
            echo "Uninstall cancelled."
            exit 0
        }
        
        echo ""
        read -p "Type 'uninstall' to proceed: " CONFIRM2
        [ "$CONFIRM2" != "uninstall" ] && {
            echo "Uninstall cancelled."
            exit 0
        }
        echo ""
    fi
    
    # Remove shownet command
    if [ -f /usr/local/bin/shownet ]; then
        sudo rm -f /usr/local/bin/shownet 2>/dev/null && \
            print_success "Removed /usr/local/bin/shownet" || \
            print_error "Could not remove /usr/local/bin/shownet"
    fi
    
    if [ -f /opt/homebrew/bin/shownet ]; then
        sudo rm -f /opt/homebrew/bin/shownet 2>/dev/null && \
            print_success "Removed /opt/homebrew/bin/shownet"
    fi
    
    # Detect OS for uninstall
    detect_os
    
    # Stop and uninstall ZeroTier
    if [ "$OS" == "macos" ]; then
        # macOS - stop service and uninstall via Homebrew
        if sudo launchctl list | grep -q com.zerotier.one 2>/dev/null; then
            sudo launchctl unload /Library/LaunchDaemons/com.zerotier.one.plist 2>/dev/null || true
            print_success "ZeroTier service stopped"
        fi
        
        if command_exists brew && brew list zerotier-one >/dev/null 2>&1; then
            brew uninstall zerotier-one 2>/dev/null && \
                print_success "ZeroTier uninstalled via Homebrew" || \
                print_error "Could not uninstall ZeroTier via Homebrew"
        fi
    elif [ "$OS" == "linux" ]; then
        # Linux - stop service and uninstall via package manager
        if systemctl is-active --quiet zerotier-one 2>/dev/null; then
            sudo systemctl stop zerotier-one 2>/dev/null || true
            sudo systemctl disable zerotier-one 2>/dev/null || true
            print_success "ZeroTier service stopped and disabled"
        fi
        
        # Try apt-get (Debian/Ubuntu)
        if command_exists apt-get; then
            if dpkg -l | grep -q zerotier-one 2>/dev/null; then
                sudo apt-get remove -y zerotier-one 2>/dev/null && \
                    print_success "ZeroTier uninstalled via apt-get" || \
                    print_error "Could not uninstall ZeroTier via apt-get"
            fi
        # Try yum (RHEL/CentOS)
        elif command_exists yum; then
            if rpm -q zerotier-one >/dev/null 2>&1; then
                sudo yum remove -y zerotier-one 2>/dev/null && \
                    print_success "ZeroTier uninstalled via yum" || \
                    print_error "Could not uninstall ZeroTier via yum"
            fi
        fi
    fi
    
    echo ""
    echo "Uninstall complete."
    echo "To reinstall, run: ./setup-shownet.sh"
    echo ""
}

shownet_main() {
    case "${1:-}" in
        info)
            shownet_info
            ;;
        join)
            shownet_join
            ;;
        leave)
            shownet_leave
            ;;
        uninstall)
            shownet_uninstall
            ;;
        *)
            echo "Usage: shownet [info|join|leave|uninstall]"
            echo ""
            echo "Commands:"
            echo "  info      - Show ZeroTier network information and your IP"
            echo "  join      - Join a ZeroTier network (will prompt for Network ID)"
            echo "  leave     - Leave a ZeroTier network (will prompt for Network ID)"
            echo "  uninstall - Remove shownet command and stop ZeroTier"
            exit 1
            ;;
    esac
}

main() {
    [ "$SHOWNET_MODE" = true ] && {
        shownet_main "$@"
        exit $?
    }
    
    detect_os
    
    if install_zerotier; then
        print_success "ZeroTier installed"
    else
        print_error "Failed to install ZeroTier"
        exit 1
    fi
    
    start_zerotier
    print_success "ZeroTier service started"
    
    if install_shownet; then
        print_success "Installed 'shownet' command"
    else
        print_error "Could not install 'shownet' command (requires sudo)"
    fi
    
    echo ""
    echo "Setup complete!"
    echo ""
    echo "Next steps:"
    echo "  shownet join  - Join a ZeroTier network"
    echo "  shownet info  - Show network information"
    echo ""
}

main "$@"

