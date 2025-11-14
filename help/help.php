<div style="margin:0 auto;">
    <fieldset style="padding: 10px; border: 2px solid #000;">
        <legend>ZeroTier Plugin Help</legend>
        <div style="overflow: hidden; padding: 10px;">
            <h2>Getting Started</h2>
            <p>
                The ZeroTier plugin allows you to connect your FPP device to ZeroTier virtual networks,
                enabling secure remote access and network connectivity.
            </p>
            
            <h3>Installation</h3>
            <p>
                When you install this plugin, it will automatically:
            </p>
            <ul>
                <li>Install ZeroTier if it's not already installed</li>
                <li>Start and enable the ZeroTier service</li>
                <li>Install the <code>shownet</code> command-line tool</li>
            </ul>
            
            <h3>Joining a Network</h3>
            <ol>
                <li>Go to the <strong>ZeroTier - Configuration</strong> page</li>
                <li>Enter your ZeroTier Network ID (16 hexadecimal characters)</li>
                <li>Click "Join Network"</li>
                <li>Authorize your device in the ZeroTier Central web interface</li>
                <li>Wait for an IP address to be assigned</li>
            </ol>
            
            <h3>Viewing Status</h3>
            <p>
                The <strong>ZeroTier - Status</strong> page shows:
            </p>
            <ul>
                <li>Your ZeroTier Node ID</li>
                <li>ZeroTier version and status</li>
                <li>All networks you're currently joined to</li>
                <li>IP addresses assigned to each network</li>
            </ul>
            
            <h3>Command Line Usage</h3>
            <p>
                For advanced users, the plugin installs a <code>shownet</code> command:
            </p>
            <ul>
                <li><code>shownet info</code> - Show network information</li>
                <li><code>shownet join</code> - Join a network (will prompt for Network ID)</li>
                <li><code>shownet leave</code> - Leave a network (will prompt for Network ID)</li>
            </ul>
            
            <h3>Authorization</h3>
            <p>
                After joining a network, you typically need to authorize your device:
            </p>
            <ol>
                <li>Go to <a href="https://my.zerotier.com/" target="_blank">ZeroTier Central</a></li>
                <li>Select your network</li>
                <li>Find your device by Node ID (shown on the Status page)</li>
                <li>Check the "Auth" checkbox to authorize the device</li>
            </ol>
            
            <h3>Troubleshooting</h3>
            <ul>
                <li><strong>ZeroTier not installed:</strong> The plugin installer should handle this automatically. If it fails, install manually: <code>curl -s https://install.zerotier.com | sudo bash</code></li>
                <li><strong>Service not running:</strong> Start with <code>sudo systemctl start zerotier-one</code></li>
                <li><strong>No IP assigned:</strong> Make sure your device is authorized in ZeroTier Central</li>
                <li><strong>Can't join network:</strong> Verify the Network ID is correct (16 hex characters)</li>
            </ul>
            
            <h3>Additional Resources</h3>
            <ul>
                <li><a href="https://www.zerotier.com/" target="_blank">ZeroTier Website</a></li>
                <li><a href="https://docs.zerotier.com/" target="_blank">ZeroTier Documentation</a></li>
                <li><a href="https://my.zerotier.com/" target="_blank">ZeroTier Central</a></li>
            </ul>
        </div>
    </fieldset>
</div>
